<?php
/**
 * ASU REMS — Extension Module
 * File: extension/services.php
 * Training & Technical Services (Section 4.2)
 *
 * Request a Service is handled inside this page through the section query string.
 * The request form uses the extension_service_requests table.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/extension_roles.php';

requireLogin();
$user = currentUser();
$role = (string)($user['role'] ?? '');
$isOffice = $role === 'extension_service_office';
$isProponent = in_array($role, ['extensionist', 'extension_coordinator'], true);

if (!$isOffice && !$isProponent) {
    redirect(BASE_URL . '/dashboard.php');
}

$sections = $isOffice ? [
    'service_requests' => [
        'title' => 'Service Requests',
        'description' => 'View and process training and technical service requests from Extensionists.',
        'details' => 'Request review, acknowledgement, and requests for additional information will be built here.'
    ],
    'schedule_assignments' => [
        'title' => 'Schedule & Assignments',
        'description' => 'Assign an appropriate person or academic unit and arrange the service schedule.',
        'details' => 'Personnel assignment, schedule setting, and notifications will be built here.'
    ],
    'completed_services' => [
        'title' => 'Completed Services',
        'description' => 'View service requests that have been completed and recorded by the ESO.',
        'details' => 'Completed service records and related confirmations will be built here.'
    ]
] : [
    'request_service' => [
        'title' => 'Request a Service',
        'description' => 'Submit a request using the official Request for Technical Services form.',
        'details' => ''
    ],
    'my_service_requests' => [
        'title' => 'My Service Requests',
        'description' => 'View the status and details of your submitted service requests.',
        'details' => 'Your submitted service requests appear below.'
    ]
];

$section = (string)($_GET['section'] ?? array_key_first($sections));
if (!isset($sections[$section])) {
    $section = array_key_first($sections);
}
$pageTitle = $sections[$section]['title'] . ' | Training & Technical Services';
$activePage = 'extension_training_services';

function serviceFormEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function serviceFormRedirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

$errors = [];
$success = '';
$draftId = 0;
$revisionId = 0;
$form = [
    'service_type' => '',
    'organization_name' => '',
    'contact_person' => trim((string)fullName($user)),
    'contact_email' => (string)($user['email'] ?? ''),
    'contact_number' => (string)($user['contact_number'] ?? ''),
    'service_title' => '',
    'service_description' => '',
    'target_participants' => '',
    'preferred_date' => '',
    'preferred_location' => '',
    'request_letter_url' => '',
    'other_service_details' => '',
    'requester_signature_name' => '',
    'assisted_by_name' => '',
];

// These choices follow the official ASU Extension and Community Services form.
$paperServiceOptions = ['Paper Editing', 'Technical Advice', 'Presentation Editing', 'Others', 'Poster Layout'];
$selectedPaperServices = [];
$requestMode = 'official';

/** Accept only HTTPS document links (not local file paths or HTTP). */
function serviceRequestSafeLetterUrl(string $value): ?string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f]/', $value)) {
        return null;
    }
    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($value);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    return $value;
}

function servicePaperChoices($json): array
{
    $decoded = json_decode((string)($json ?? ''), true);
    if (!is_array($decoded)) {
        return [];
    }
    $allowed = ['Paper Editing', 'Technical Advice', 'Presentation Editing', 'Others', 'Poster Layout'];
    return array_values(array_unique(array_intersect($allowed, array_filter($decoded, 'is_string'))));
}

/**
 * Identify Request Letter submissions even if an older saved record was
 * assigned a generic service type. The old form required at least one
 * selected service when submitted, so a submitted [] entry is treated as a
 * request-letter record rather than showing an empty Types of Services row.
 */
function serviceIsLetterRequest(array $record): bool
{
    $type = strtolower(trim((string)($record['service_type'] ?? '')));
    if ($type === 'request letter') {
        return true;
    }
    if (serviceRequestSafeLetterUrl((string)($record['request_letter_url'] ?? '')) !== null) {
        return true;
    }
    if (stripos(trim((string)($record['service_title'] ?? '')), 'Request Letter') === 0) {
        return true;
    }
    $status = (string)($record['status'] ?? '');
    if ($status !== 'Draft' && ($record['requested_services_json'] ?? null) !== null
        && servicePaperChoices($record['requested_services_json']) === []) {
        return true;
    }
    return false;
}


if ($isProponent && $section === 'request_service') {
    if (empty($_SESSION['service_request_csrf'])) {
        $_SESSION['service_request_csrf'] = bin2hex(random_bytes(32));
    }
    $csrfToken = (string)$_SESSION['service_request_csrf'];
    $draftId = filter_input(INPUT_GET, 'draft', FILTER_VALIDATE_INT) ?: 0;
    $revisionId = filter_input(INPUT_GET, 'revise', FILTER_VALIDATE_INT) ?: 0;
    $draft = null;
    $revision = null;

    if ($draftId > 0 || $revisionId > 0) {
        $requestedStatus = $draftId > 0 ? 'Draft' : 'Needs Information';
        $requestRecordId = $draftId > 0 ? $draftId : $revisionId;
        $draftStmt = $pdo->prepare('SELECT * FROM extension_service_requests WHERE request_id = ? AND requester_id = ? AND status = ? LIMIT 1');
        $draftStmt->execute([$requestRecordId, (int)$user['user_id'], $requestedStatus]);
        $existingRequest = $draftStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$existingRequest) {
            http_response_code(404);
            exit($draftId > 0 ? 'Draft service request not found.' : 'Service request is not awaiting additional information.');
        }
        if ($draftId > 0) {
            $draft = $existingRequest;
        } else {
            $revision = $existingRequest;
        }
        foreach ($form as $key => $_) {
            if (isset($existingRequest[$key])) {
                $form[$key] = (string)$existingRequest[$key];
            }
        }
        $selectedPaperServices = servicePaperChoices($existingRequest['requested_services_json'] ?? null);
        $requestMode = serviceIsLetterRequest($existingRequest) ? 'letter' : 'official';
    }

    // A viewed link is registered for this particular new request, draft, or revision.
    $letterScope = (int)$user['user_id'] . ':' . ($draftId > 0 ? 'draft:' . $draftId : ($revisionId > 0 ? 'revise:' . $revisionId : 'new'));

    // The eye button submits the current form to a new tab (native browser POST).
    // This page records the click in the session, then redirects that tab to the
    // HTTPS document. No AJAX or JSON parsing is needed, so HTML redirects or
    // login screens cannot produce a client-side "Unexpected token" error.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'view_request_letter') {
        header('Cache-Control: no-store, private');
        header('Content-Type: text/plain; charset=UTF-8');
        if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            exit('Session expired. Return to Request a Service and refresh the page.');
        }
        $requestedLink = is_string($_POST['request_letter_url'] ?? null) ? trim($_POST['request_letter_url']) : '';
        $safeLink = serviceRequestSafeLetterUrl($requestedLink);
        if ($safeLink === null) {
            http_response_code(422);
            exit('Enter a valid HTTPS request letter link.');
        }
        $_SESSION['service_letter_views'][$letterScope] = [
            'hash' => hash('sha256', $safeLink),
            'time' => time()
        ];
        // Release the PHP session before the coordinator submits the request
        // from the original tab. This also ensures the eye-click hash is saved.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ' . $safeLink, true, 303);
        exit;
    }

    // The form's values are mapped onto the legacy columns so that request
    // numbers, search, scheduling, status transitions and ESO lists still work.
    // Four additional columns preserve the complete official form information.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
            $errors[] = 'Your session token is invalid. Refresh the page and try again.';
        }
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['save_draft', 'submit_request', 'edit_resubmit'], true)) {
            $errors[] = 'Invalid form action.';
        }
        if ($revisionId > 0 && $action !== 'edit_resubmit') {
            $errors[] = 'Submit the updated information to return this request to the ESO.';
        }
        $requestMode = is_string($_POST['request_mode'] ?? null) ? (string)$_POST['request_mode'] : '';
        if (!in_array($requestMode, ['official', 'letter'], true)) {
            $errors[] = 'Select a valid request form type.';
            $requestMode = 'official';
        }
        foreach (['contact_person', 'organization_name', 'preferred_date', 'contact_number',
                  'service_description', 'other_service_details', 'requester_signature_name',
                  'assisted_by_name', 'request_letter_url'] as $key) {
            $postedValue = $_POST[$key] ?? '';
            $form[$key] = is_string($postedValue) ? trim($postedValue) : '';
        }

        $postedServices = $requestMode === 'official' ? ($_POST['requested_services'] ?? []) : [];
        if (!is_array($postedServices)) {
            $errors[] = 'Choose valid technical services.';
            $postedServices = [];
        }
        $selectedPaperServices = [];
        foreach ($postedServices as $serviceName) {
            if (!is_string($serviceName) || !in_array($serviceName, $paperServiceOptions, true)) {
                $errors[] = 'Invalid technical service selected.';
                continue;
            }
            if (!in_array($serviceName, $selectedPaperServices, true)) {
                $selectedPaperServices[] = $serviceName;
            }
        }
        if ($requestMode !== 'official' || !in_array('Others', $selectedPaperServices, true)) {
            $form['other_service_details'] = '';
        }
        if ($requestMode !== 'letter') {
            $form['request_letter_url'] = '';
        }
        $isSubmitting = in_array($action, ['submit_request', 'edit_resubmit'], true);
        if ($isSubmitting) {
            $required = [
                'contact_person' => 'Enter your name.',
                'organization_name' => 'Enter the agency, school, or address.',
                'preferred_date' => 'Enter the date.',
                'contact_number' => 'Enter your contact number.',
                'service_description' => 'Enter the purpose of the request.',
                'requester_signature_name' => 'Enter your full name.'
            ];
            foreach ($required as $key => $message) {
                if ($form[$key] === '') {
                    $errors[] = $message;
                }
            }
            if ($requestMode === 'official') {
                if (!$selectedPaperServices) {
                    $errors[] = 'Select at least one type of technical service.';
                }
                if (in_array('Others', $selectedPaperServices, true) && $form['other_service_details'] === '') {
                    $errors[] = 'Specify the other technical service requested.';
                }
            } else {
                $safeLetterUrl = serviceRequestSafeLetterUrl($form['request_letter_url']);
                if ($safeLetterUrl === null) {
                    $errors[] = 'Enter a valid HTTPS request letter link.';
                } else {
                    $viewedLetter = $_SESSION['service_letter_views'][$letterScope] ?? null;
                    if (!is_array($viewedLetter)
                        || !hash_equals((string)($viewedLetter['hash'] ?? ''), hash('sha256', $safeLetterUrl))
                        || time() - (int)($viewedLetter['time'] ?? 0) > 1800) {
                        $errors[] = 'You must open the link using the eye button before submitting.';
                    }
                }
            }
        }
        if ($requestMode === 'letter' && $form['request_letter_url'] !== ''
            && serviceRequestSafeLetterUrl($form['request_letter_url']) === null) {
            $errors[] = 'The request letter must be a valid HTTPS link.';
        }
        if ($form['preferred_date'] !== '') {
            $date = DateTime::createFromFormat('!Y-m-d', $form['preferred_date']);
            if (!$date || $date->format('Y-m-d') !== $form['preferred_date']) {
                $errors[] = 'Enter a valid date.';
            }
        }
        foreach (['contact_person' => 160, 'organization_name' => 180, 'contact_number' => 40,
                  'service_description' => 5000, 'other_service_details' => 500,
                  'requester_signature_name' => 160, 'assisted_by_name' => 160] as $key => $limit) {
            if ((function_exists('mb_strlen') ? mb_strlen($form[$key], 'UTF-8') : iconv_strlen($form[$key], 'UTF-8')) > $limit) {
                $errors[] = 'The ' . str_replace('_', ' ', $key) . ' field is too long.';
            }
        }

        if (!$errors) {
            $status = $isSubmitting ? 'Submitted' : 'Draft';
            $summaryChoices = array_map(static function (string $name) use ($form): string {
                return $name === 'Others' && $form['other_service_details'] !== ''
                    ? 'Others: ' . $form['other_service_details'] : $name;
            }, $selectedPaperServices);
            $summary = $requestMode === 'letter'
                ? 'Request Letter' . ($form['organization_name'] !== '' ? ' - ' . $form['organization_name'] : '')
                : ($summaryChoices ? implode(', ', $summaryChoices) : 'Technical Services Request');
            // service_title is the standard listing/search field (max. 180 chars).
            $summary = function_exists('mb_substr') ? mb_substr($summary, 0, 180, 'UTF-8') : iconv_substr($summary, 0, 180, 'UTF-8');
            $requestValues = [
                $requestMode === 'letter' ? 'Request Letter' : 'Technical Service',
                $form['organization_name'] !== '' ? $form['organization_name'] : null,
                $form['contact_person'] !== '' ? $form['contact_person'] : null,
                (string)($user['email'] ?? '') ?: null,
                $form['contact_number'] !== '' ? $form['contact_number'] : null,
                $summary,
                $form['service_description'] !== '' ? $form['service_description'] : null,
                null, // The official form has no participant-count field.
                $form['preferred_date'] !== '' ? $form['preferred_date'] : null,
                $form['organization_name'] !== '' ? $form['organization_name'] : null,
                $requestMode === 'letter' && $form['request_letter_url'] !== '' ? $form['request_letter_url'] : null,
                json_encode($requestMode === 'official' ? $selectedPaperServices : [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $form['other_service_details'] !== '' ? $form['other_service_details'] : null,
                $form['requester_signature_name'] !== '' ? $form['requester_signature_name'] : null,
                $form['assisted_by_name'] !== '' ? $form['assisted_by_name'] : null,
            ];

            $assignments = 'service_type = ?, organization_name = ?, contact_person = ?, contact_email = ?, contact_number = ?, service_title = ?, service_description = ?, target_participants = ?, preferred_date = ?, preferred_location = ?, request_letter_url = ?, requested_services_json = ?, other_service_details = ?, requester_signature_name = ?, assisted_by_name = ?';
            try {
                if ($revisionId > 0) {
                    $saveStmt = $pdo->prepare("UPDATE extension_service_requests SET $assignments, status = 'Submitted', submitted_at = NOW(), office_remarks = NULL, updated_at = NOW() WHERE request_id = ? AND requester_id = ? AND status = 'Needs Information'");
                    $saveStmt->execute([...$requestValues, $revisionId, (int)$user['user_id']]);
                    $requestId = $revisionId;
                } elseif ($draftId > 0) {
                    $saveStmt = $pdo->prepare("UPDATE extension_service_requests SET $assignments, status = ?, submitted_at = CASE WHEN ? = 'Submitted' THEN NOW() ELSE NULL END, updated_at = NOW() WHERE request_id = ? AND requester_id = ? AND status = 'Draft'");
                    $saveStmt->execute([...$requestValues, $status, $status, $draftId, (int)$user['user_id']]);
                    $requestId = $draftId;
                } else {
                    $saveStmt = $pdo->prepare('INSERT INTO extension_service_requests (requester_id, service_type, organization_name, contact_person, contact_email, contact_number, service_title, service_description, target_participants, preferred_date, preferred_location, request_letter_url, requested_services_json, other_service_details, requester_signature_name, assisted_by_name, status, submitted_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = \'Submitted\' THEN NOW() ELSE NULL END, NOW(), NOW())');
                    $saveStmt->execute([(int)$user['user_id'], ...$requestValues, $status, $status]);
                    $requestId = (int)$pdo->lastInsertId();
                }
                $requestCode = 'SR-' . date('Y') . '-' . str_pad((string)$requestId, 5, '0', STR_PAD_LEFT);
                $codeStmt = $pdo->prepare('UPDATE extension_service_requests SET request_code = ? WHERE request_id = ? AND requester_id = ?');
                $codeStmt->execute([$requestCode, $requestId, (int)$user['user_id']]);
                if ($status === 'Submitted') {
                    unset($_SESSION['service_letter_views'][$letterScope]);
                }
                serviceFormRedirect(BASE_URL . '/extension/services.php?section=request_service&saved=' . ($status === 'Draft' ? 'draft' : 'submitted'));
            } catch (PDOException $exception) {
                $errors[] = 'Could not save the request. Import the supplied Technical Services SQL update in phpMyAdmin, then try again.';
            }
        }
    }
    if (isset($_GET['saved'])) {
        $success = $_GET['saved'] === 'submitted'
            ? 'Your technical service request has been submitted to the ESO.'
            : 'Your technical service request draft has been saved.';
    }
}

$serviceStatuses = ['Draft', 'Submitted', 'Acknowledged', 'Needs Information', 'Assigned', 'Scheduled', 'Ongoing', 'Completed'];
$myRequestErrors = [];

/* ESO processing actions for the three Section 4.2 office pages. */
$officeErrors = [];
$officeSuccess = '';
$officeRows = [];
$officeSelectedRequest = 0;
$officeRequestDetails = null;
$officeSearch = trim((string)($_GET['q'] ?? ''));
$officeFilterStatus = trim((string)($_GET['status'] ?? ''));

if ($isOffice) {
    if (empty($_SESSION['eso_service_csrf'])) {
        $_SESSION['eso_service_csrf'] = bin2hex(random_bytes(32));
    }
    $officeCsrf = (string)$_SESSION['eso_service_csrf'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($officeCsrf, (string)($_POST['csrf_token'] ?? ''))) {
            $officeErrors[] = 'Your session token is invalid. Refresh the page and try again.';
        } else {
            $action = (string)($_POST['action'] ?? '');
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
            if ($requestId < 1) {
                $officeErrors[] = 'The selected service request is invalid.';
            }

            $findRequest = $pdo->prepare('SELECT request_id, status FROM extension_service_requests WHERE request_id = ? LIMIT 1');
            if (!$officeErrors) {
                $findRequest->execute([$requestId]);
                $actionRequest = $findRequest->fetch(PDO::FETCH_ASSOC);
                if (!$actionRequest) {
                    $officeErrors[] = 'The service request could not be found.';
                }
            }

            if (!$officeErrors) {
                $currentStatus = (string)$actionRequest['status'];
                $officeRemarks = trim((string)($_POST['office_remarks'] ?? ''));
                try {
                    if ($action === 'acknowledge' && $currentStatus === 'Submitted') {
                        $stmt = $pdo->prepare("UPDATE extension_service_requests SET status = 'Acknowledged', acknowledged_at = NOW(), acknowledged_by = ?, updated_at = NOW() WHERE request_id = ? AND status = 'Submitted'");
                        $stmt->execute([(int)$user['user_id'], $requestId]);
                        $officeSuccess = 'Receipt acknowledged.';
                    } elseif ($action === 'request_information' && in_array($currentStatus, ['Submitted', 'Acknowledged', 'Needs Information'], true)) {
                        if ($officeRemarks === '') {
                            $officeErrors[] = 'Enter the information needed from the Extensionist.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE extension_service_requests SET status = 'Needs Information', office_remarks = ?, updated_at = NOW() WHERE request_id = ?");
                            $stmt->execute([$officeRemarks, $requestId]);
                            $officeSuccess = 'Additional information requested.';
                        }
                    } elseif ($action === 'save_schedule' && in_array($currentStatus, ['Acknowledged', 'Assigned', 'Scheduled'], true)) {
                        $unit = trim((string)($_POST['assigned_unit'] ?? ''));
                        $personnel = trim((string)($_POST['assigned_personnel'] ?? ''));
                        $date = trim((string)($_POST['scheduled_date'] ?? ''));
                        $time = trim((string)($_POST['scheduled_time'] ?? ''));
                        $location = trim((string)($_POST['scheduled_location'] ?? ''));
                        $validDate = DateTime::createFromFormat('Y-m-d', $date);
                        $validTime = DateTime::createFromFormat('H:i', $time);
                        if ($unit === '' || $personnel === '' || !$validDate || $validDate->format('Y-m-d') !== $date || !$validTime || $validTime->format('H:i') !== $time || $location === '') {
                            $officeErrors[] = 'Complete the assigned unit, personnel, valid schedule date and time, and service location.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE extension_service_requests SET assigned_unit = ?, assigned_personnel = ?, scheduled_date = ?, scheduled_time = ?, scheduled_location = ?, status = 'Scheduled', office_remarks = NULL, updated_at = NOW() WHERE request_id = ?");
                            $stmt->execute([$unit, $personnel, $date, $time, $location, $requestId]);
                            $officeSuccess = 'Assignment and schedule saved.';
                        }
                    } elseif ($action === 'start_service' && $currentStatus === 'Scheduled') {
                        $stmt = $pdo->prepare("UPDATE extension_service_requests SET status = 'Ongoing', updated_at = NOW() WHERE request_id = ? AND status = 'Scheduled'");
                        $stmt->execute([$requestId]);
                        $officeSuccess = 'Service marked as ongoing.';
                    } elseif ($action === 'mark_completed' && in_array($currentStatus, ['Scheduled', 'Ongoing'], true)) {
                        $stmt = $pdo->prepare("UPDATE extension_service_requests SET status = 'Completed', completed_at = NOW(), completed_by = ?, updated_at = NOW() WHERE request_id = ? AND status IN ('Scheduled', 'Ongoing') AND extensionist_completed_at IS NOT NULL");
                        $stmt->execute([(int)$user['user_id'], $requestId]);
                        if ($stmt->rowCount() < 1) {
                            $officeErrors[] = 'The Extensionist must confirm completion before ESO can close this request.';
                        } else {
                            $officeSuccess = 'Service marked as completed.';
                        }
                    } else {
                        $officeErrors[] = 'This action is not available for the request’s current status.';
                    }
                } catch (PDOException $exception) {
                    $officeErrors[] = 'The action could not be saved. Confirm that the Section 4.2 database update has been applied.';
                }
            }

            if (!$officeErrors) {
                serviceFormRedirect(BASE_URL . '/extension/services.php?section=' . rawurlencode($section) . '&saved=' . rawurlencode($action));
            }
        }
    }

    if (isset($_GET['saved'])) {
        $officeSuccess = match ((string)$_GET['saved']) {
            'acknowledge' => 'Receipt acknowledged.',
            'request_information' => 'Additional information requested.',
            'save_schedule' => 'Assignment and schedule saved.',
            'start_service' => 'Service marked as ongoing.',
            'mark_completed' => 'Service marked as completed.',
            default => '',
        };
    }

    $officeSelectedRequest = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT) ?: 0;
    if ($officeSelectedRequest > 0) {
        $detailsStmt = $pdo->prepare('SELECT * FROM extension_service_requests WHERE request_id = ? LIMIT 1');
        $detailsStmt->execute([$officeSelectedRequest]);
        $officeRequestDetails = $detailsStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $where = [];
    $params = [];
    if ($section === 'service_requests') {
        $where[] = "status IN ('Submitted', 'Acknowledged', 'Needs Information')";
    } elseif ($section === 'schedule_assignments') {
        $where[] = "status IN ('Acknowledged', 'Assigned', 'Scheduled', 'Ongoing') AND extensionist_completed_at IS NULL";
    } else {
        $where[] = "(status = 'Completed' OR (status IN ('Scheduled', 'Ongoing') AND extensionist_completed_at IS NOT NULL))";
    }
    if ($officeSearch !== '') {
        $where[] = '(request_code LIKE ? OR service_title LIKE ? OR organization_name LIKE ?)';
        $like = '%' . $officeSearch . '%';
        array_push($params, $like, $like, $like);
    }
    if ($officeFilterStatus !== '' && in_array($officeFilterStatus, $serviceStatuses, true)) {
        $where[] = 'status = ?';
        $params[] = $officeFilterStatus;
    }
    $officeListStmt = $pdo->prepare('SELECT request_id, request_code, service_type, organization_name, service_title, preferred_date, preferred_location, assigned_unit, assigned_personnel, scheduled_date, scheduled_time, scheduled_location, status, submitted_at, created_at, extensionist_completed_at FROM extension_service_requests WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(submitted_at, created_at) DESC, request_id DESC');
    $officeListStmt->execute($params);
    $officeRows = $officeListStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($isProponent && $section === 'my_service_requests' && empty($_SESSION['service_request_csrf'])) {
    $_SESSION['service_request_csrf'] = bin2hex(random_bytes(32));
}
if ($isProponent && $section === 'my_service_requests' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_service_completed') {
    if (empty($_SESSION['service_request_csrf']) || !hash_equals((string)$_SESSION['service_request_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $myRequestErrors[] = 'Your session token is invalid. Refresh the page and try again.';
    } else {
        $confirmId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $confirmStmt = $pdo->prepare("UPDATE extension_service_requests SET extensionist_completed_at = NOW(), updated_at = NOW() WHERE request_id = ? AND requester_id = ? AND status IN ('Scheduled', 'Ongoing') AND extensionist_completed_at IS NULL");
        $confirmStmt->execute([$confirmId, (int)$user['user_id']]);
        if ($confirmStmt->rowCount() > 0) {
            serviceFormRedirect(BASE_URL . '/extension/services.php?section=my_service_requests&saved=completion_confirmed');
        }
        $myRequestErrors[] = 'This service cannot be confirmed as completed yet.';
    }
}

$myRequests = [];
$myRequestCounts = [];
$mySelectedStatus = '';
$mySearchTerm = '';
$myViewId = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT) ?: 0;
$myViewRequest = null;

function myServiceStatusLabel(string $status): string
{
    return match ($status) {
        'Acknowledged' => 'Received by ESO',
        'Needs Information' => 'Needs Information',
        default => $status,
    };
}

function myServiceStatusBadge(string $status): string
{
    return match ($status) {
        'Draft' => 'my-sr-status-draft',
        'Submitted', 'Needs Information' => 'my-sr-status-pending',
        'Acknowledged', 'Assigned', 'Scheduled' => 'my-sr-status-processing',
        'Ongoing' => 'my-sr-status-ongoing',
        'Completed' => 'my-sr-status-completed',
        default => 'my-sr-status-neutral',
    };
}

function myServiceFormattedDate(?string $value, string $format = 'M d, Y'): string
{
    if (!$value) {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date($format, $timestamp) : '—';
}

function myServiceSafeHttpsUrl(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $parts = parse_url($value);
    if (!$parts || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
        return null;
    }
    return $value;
}

if ($isProponent && $section === 'my_service_requests') {
    $mySelectedStatus = trim((string)($_GET['status'] ?? ''));
    if ($mySelectedStatus !== '' && !in_array($mySelectedStatus, $serviceStatuses, true)) {
        $mySelectedStatus = '';
    }
    $mySearchTerm = trim((string)($_GET['q'] ?? ''));

    if ($myViewId > 0) {
        $viewStmt = $pdo->prepare("
            SELECT request_id, request_code, service_type, organization_name,
                   contact_person, contact_email, contact_number, service_title,
                   service_description, target_participants, preferred_date,
                   preferred_location, request_letter_url, requested_services_json,
                   other_service_details, requester_signature_name, assisted_by_name,
                   status, submitted_at,
                   created_at, updated_at, office_remarks, assigned_unit,
                   assigned_personnel, scheduled_date, scheduled_time,
                   scheduled_location, extensionist_completed_at
            FROM extension_service_requests
            WHERE request_id = ? AND requester_id = ?
            LIMIT 1
        ");
        $viewStmt->execute([$myViewId, (int)$user['user_id']]);
        $myViewRequest = $viewStmt->fetch(PDO::FETCH_ASSOC);
        if (!$myViewRequest) {
            http_response_code(404);
        }
    }

    $myWhere = ['requester_id = ?'];
    $myParams = [(int)$user['user_id']];
    if ($mySelectedStatus !== '') {
        $myWhere[] = 'status = ?';
        $myParams[] = $mySelectedStatus;
    }
    if ($mySearchTerm !== '') {
        $myWhere[] = '(request_code LIKE ? OR service_title LIKE ? OR organization_name LIKE ?)';
        $myLike = '%' . $mySearchTerm . '%';
        array_push($myParams, $myLike, $myLike, $myLike);
    }

    $myListStmt = $pdo->prepare("
        SELECT request_id, request_code, service_type, organization_name,
               service_title, preferred_date, preferred_location,
               status, submitted_at, created_at, updated_at, extensionist_completed_at
        FROM extension_service_requests
        WHERE " . implode(' AND ', $myWhere) . "
        ORDER BY COALESCE(submitted_at, created_at) DESC, request_id DESC
    ");
    $myListStmt->execute($myParams);
    $myRequests = $myListStmt->fetchAll(PDO::FETCH_ASSOC);

    $myCountStmt = $pdo->prepare("
        SELECT COUNT(*) AS total_count,
               SUM(status = 'Submitted') AS submitted_count,
               SUM(status = 'Scheduled') AS scheduled_count,
               SUM(status = 'Completed') AS completed_count
        FROM extension_service_requests
        WHERE requester_id = ?
    ");
    $myCountStmt->execute([(int)$user['user_id']]);
    $myRequestCounts = $myCountStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (($_GET['saved'] ?? '') === 'completion_confirmed') {
        $success = 'You confirmed that the service has been completed. The ESO will finalize the record.';
    }
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
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.card,.asu-card,.asu-panel,.panel,.extension-review-card,.call-card,.extension-form-card,.extension-form-section,.filter-card,.eso-queue-panel,.extension-panel,.extension-status-overview,.extension-document-summary,.extension-document-summary-tile,.extension-overview-row,.extension-official-documents,.workflow-card,.content-block,.document-item,.extension-document-viewer,.extension-preview-message-box,.monitoring-card,.monitoring-filter-card,.monitor-flow-card,.monitor-flow-detail,.monitor-report-card,.monitor-report-detail,.eso-monitor-card,.eso-monitor-detail,.service-request-form-card,.eso-service-card,.eso-service-details,.my-sr-card,.my-sr-details,.my-sr-filter-card,.extension-report-card,.extension-report-details,.call-meta,.extension-review-meta-item,.review-link-summary,.review-form-row,.extension-summary-meta,.my-sr-detail,.monitoring-note,.service-request-note,.extension-upload-intro,.upload-box,.extension-upload-control,.extension-call-link,.extension-form-section-title,.review-unavailable,.monitoring-list-filters,.monitoring-list-toolbar,.my-sr-toolbar,.eso-service-toolbar) {
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

html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.service-request-form-card,.eso-service-card,.my-sr-card,.my-sr-filter-card,.eso-service-toolbar,.my-sr-toolbar,.my-sr-details,.eso-service-details) {background:#172f23!important;border-color:#3e634b!important;color:#edf7ed!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.service-request-heading,.my-sr-records-head,.my-sr-head-title,.eso-service-toolbar) {background:#1d3b2b!important;color:#eef7ef!important;border-color:#43664e!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.my-sr-title,.my-sr-detail strong,.eso-service-details strong,.service-request-heading h2) {color:#eff9ef!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.my-sr-description,.my-sr-code,.my-sr-detail .small,.service-request-help) {color:#c2d6c6!important}

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
                    <span class="rems-view-eyebrow">Training &amp; Technical Services</span>
                    <h1 id="remsExtensionPageTitle"><?= e($sections[$section]['title']); ?></h1>
                    <p><?= e($sections[$section]['description']); ?></p>
                </div>
                <a href="<?= BASE_URL; ?>/extension/index.php" class="btn btn-outline-asu rems-header-back rems-top-yellow-btn">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Extension
                </a>
            </div>

            <?php if ($isProponent && $section === 'request_service'): ?>
                <style>
                    .service-request-form-card{background:#fff;border:1px solid rgba(0,107,46,.15);border-radius:14px;box-shadow:0 12px 30px rgba(0,79,34,.06)}
                    .service-request-form-card .card-body{padding:24px}
                    .service-request-help{color:#68756c;font-size:.82rem}
                    .service-request-eye{width:42px;min-width:42px}
                    .service-request-note{background:#f5f8f5;border:1px solid #e5ece6;border-radius:9px;padding:12px 14px;color:#59665d;font-size:.88rem}
                    /* Uniform form section heading: green icon tile like Extension create.php */
                    .service-request-heading{display:flex;align-items:center;gap:12px;min-width:0}
                    .service-request-heading-icon{width:40px;height:40px;min-width:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:10px;background:#edf7ef;color:#007a33;font-size:18px}
                    .service-request-heading h2{margin:0!important;line-height:1.35!important}
                    /* Official Technical Services form: compact checkbox tiles, not a table. */
                    .service-paper-heading{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:10px}
                    .service-paper-choices{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
                    .service-paper-choice{display:flex;align-items:center;gap:10px;min-height:46px;padding:11px 13px;border:1px solid #d8e1da;border-radius:10px;background:#fbfdfb;cursor:pointer;color:#28392d;font-size:11px;font-weight:700}
                    .service-paper-choice:has(input:checked){background:#eaf5ec;border-color:#8cbca0;color:#00602c}
                    .service-paper-choice input{width:16px;height:16px;flex:0 0 16px;margin:0;accent-color:#007a33;cursor:pointer}
                    .service-paper-other[hidden]{display:none!important}
                    .service-request-mode-switch{margin-bottom:22px;padding:15px 16px;border:1px solid #e1eae2;border-radius:12px;background:#f9fcf9}
                    .service-request-mode-switch .service-request-mode-label{display:block;font-size:10px;font-weight:800;color:#365640;margin-bottom:10px}
                    .service-request-mode-buttons{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
                    .rems-view-shell .service-request-mode-button{display:flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:10px;border:1px solid #cddfd1;border-radius:10px;background:#fff;color:#00652c;font:800 11px/1.35 'Century Gothic',sans-serif;cursor:pointer;box-shadow:none;transition:none!important}
                    .rems-view-shell .service-request-mode-button[aria-pressed='true']{background:#007a33;color:#fff;border-color:#007a33}
                    .service-request-mode-panel[hidden]{display:none!important}
                    .service-letter-view-row{display:flex;align-items:stretch;gap:9px}
                    .service-letter-view-row .form-control{flex:1 1 0;min-width:0}
                    .rems-view-shell .service-letter-view-eye{width:44px!important;min-width:44px!important;height:43px!important;min-height:43px!important;padding:0!important;background:#fff;border:1px solid #cce0d1;color:#007a33;border-radius:10px!important}
                    .rems-view-shell .service-letter-view-eye:disabled{opacity:.45;cursor:not-allowed}
                    .service-letter-hint{display:block;margin-top:6px;font-size:10px;color:#758379}
                    html[data-rems-theme='dark'] body .main-page.rems-extension-dark-scope .service-request-mode-switch{background:#1b392a;border-color:#43614b}
                    html[data-rems-theme='dark'] body .main-page.rems-extension-dark-scope .service-request-mode-label{color:#d3ead8}
                    html[data-rems-theme='dark'] body .main-page.rems-extension-dark-scope .service-request-mode-button:not([aria-pressed='true']){background:#203e2d;color:#d9f0df;border-color:#51745a}
                    html[data-rems-theme='dark'] body .main-page.rems-extension-dark-scope .service-request-mode-button[aria-pressed='true']{background:#087a33;color:white;border-color:#69a57c}
                    html[data-rems-theme='dark'] body .main-page.rems-extension-dark-scope .service-letter-hint{color:#bdd2c3}
                    @media(max-width:760px){.service-paper-choices{grid-template-columns:repeat(2,minmax(0,1fr))}}
                    @media(max-width:480px){.service-paper-choices,.service-request-mode-buttons{grid-template-columns:minmax(0,1fr)}}
                    html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .service-paper-choice{background:#1c392b!important;border-color:#4c7158!important;color:#e6f2e8!important}
                    html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .service-paper-choice:has(input:checked){background:#244d35!important;border-color:#7eb08b!important;color:#f0fff3!important}
                    @media(max-width:600px){.service-request-form-card .card-body{padding:17px}}
                </style>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success" role="status"><?= serviceFormEscape($success); ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Please review the following:</strong>
                        <ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= serviceFormEscape($error); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <?php if ($revisionId > 0 && !empty($revision['office_remarks'])): ?><div class="alert alert-warning"><strong>Additional information requested by ESO:</strong><div class="mt-1"><?= serviceFormEscape($revision['office_remarks']); ?></div></div><?php endif; ?>
                <form method="post" action="<?= BASE_URL; ?>/extension/services.php?section=request_service<?= $draftId ? '&draft=' . (int)$draftId : ($revisionId ? '&revise=' . (int)$revisionId : ''); ?>" class="card service-request-form-card" novalidate>
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                            <div class="service-request-heading">
                                <span class="service-request-heading-icon" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
                                <h2 class="h6 fw-bold mb-0">Request for Technical Services</h2>
                            </div>
                            <a href="<?= BASE_URL; ?>/extension/services.php?section=my_service_requests" class="btn btn-sm btn-outline-asu rems-top-white-btn">
                                <i class="bi bi-list-ul me-1" aria-hidden="true"></i> My Service Requests
                            </a>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= serviceFormEscape($csrfToken); ?>">
                        <input type="hidden" name="request_mode" id="serviceRequestMode" value="<?= serviceFormEscape($requestMode); ?>">
                        <div class="service-request-mode-switch">
                            <span class="service-request-mode-label">Choose Request Form</span>
                            <div class="service-request-mode-buttons" role="group" aria-label="Choose request form type">
                                <button type="button" class="service-request-mode-button" data-request-mode="official" aria-pressed="<?= $requestMode === 'official' ? 'true' : 'false'; ?>"><i class="bi bi-ui-checks" aria-hidden="true"></i> Technical Services Form</button>
                                <button type="button" class="service-request-mode-button" data-request-mode="letter" aria-pressed="<?= $requestMode === 'letter' ? 'true' : 'false'; ?>"><i class="bi bi-link-45deg" aria-hidden="true"></i> Request Letter Link</button>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="contact_person">Name <span class="text-danger">*</span></label>
                                <input class="form-control" id="contact_person" name="contact_person" maxlength="160" value="<?= serviceFormEscape($form['contact_person']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="organization_name">Agency / School / Address <span class="text-danger">*</span></label>
                                <input class="form-control" id="organization_name" name="organization_name" maxlength="180" value="<?= serviceFormEscape($form['organization_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="preferred_date">Date <span class="text-danger">*</span></label>
                                <input class="form-control" type="date" id="preferred_date" name="preferred_date" value="<?= serviceFormEscape($form['preferred_date']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="contact_number">Contact Number <span class="text-danger">*</span></label>
                                <input class="form-control" type="tel" id="contact_number" name="contact_number" maxlength="40" value="<?= serviceFormEscape($form['contact_number']); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="service_description">Purpose <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="service_description" name="service_description" rows="3" maxlength="5000" placeholder="State the purpose of the technical service request." required><?= serviceFormEscape($form['service_description']); ?></textarea>
                            </div>
                            <div class="col-12 service-request-mode-panel" id="serviceOfficialServices" <?= $requestMode === 'letter' ? 'hidden' : ''; ?>>
                                <div class="service-paper-heading">
                                    <span class="form-label fw-semibold mb-0">Types of Services <span class="text-danger">*</span></span>
                                    <span class="service-request-help">Please check the appropriate box. You may select more than one.</span>
                                </div>
                                <div class="service-paper-choices" role="group" aria-label="Types of technical services">
                                    <?php foreach ($paperServiceOptions as $option): ?>
                                        <label class="service-paper-choice">
                                            <input type="checkbox" name="requested_services[]" value="<?= serviceFormEscape($option); ?>" <?= in_array($option, $selectedPaperServices, true) ? 'checked' : ''; ?>>
                                            <span><?= serviceFormEscape($option); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="service-paper-other mt-3" id="servicePaperOtherBlock" <?= !in_array('Others', $selectedPaperServices, true) ? 'hidden' : ''; ?>>
                                    <label class="form-label fw-semibold" for="other_service_details">Others (Specify) <span class="text-danger">*</span></label>
                                    <input class="form-control" id="other_service_details" name="other_service_details" maxlength="500" value="<?= serviceFormEscape($form['other_service_details']); ?>" placeholder="Specify the other technical service">
                                </div>
                            </div>
                            <div class="col-12 service-request-mode-panel" id="serviceRequestLetterPanel" <?= $requestMode !== 'letter' ? 'hidden' : ''; ?>>
                                <label class="form-label fw-semibold" for="request_letter_url">Upload Request Letter (HTTPS Link) <span class="text-danger">*</span></label>
                                <div class="service-letter-view-row">
                                    <input class="form-control" type="url" id="request_letter_url" name="request_letter_url" maxlength="2048" inputmode="url" placeholder="https://..." value="<?= serviceFormEscape($form['request_letter_url']); ?>" <?= $requestMode === 'letter' ? 'required' : ''; ?>>
                                    <button class="btn service-letter-view-eye" id="serviceRequestLetterEye" type="submit" name="action" value="view_request_letter" formtarget="_blank" formnovalidate title="Open request letter" aria-label="Open request letter"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                </div>
                                <span class="service-letter-hint" id="serviceLetterHint" aria-live="polite">You must open the link using the eye button before submitting. HTTPS links only.</span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="requester_signature_name">Full Name <span class="text-danger">*</span></label>
                                <input class="form-control" id="requester_signature_name" name="requester_signature_name" maxlength="160" value="<?= serviceFormEscape($form['requester_signature_name']); ?>" placeholder="Enter the requester's name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="assisted_by_name">Assisted by (Full Name)</label>
                                <input class="form-control" id="assisted_by_name" name="assisted_by_name" maxlength="160" value="<?= serviceFormEscape($form['assisted_by_name']); ?>" placeholder="Enter the assisting person's name, if applicable">
                            </div>
                        </div>
                        <div class="service-request-actions">
                            <a class="btn btn-outline-asu service-request-cancel" href="<?= BASE_URL; ?>/extension/index.php">Cancel</a>
                            <?php if (!$revisionId): ?>
                                <button class="btn btn-outline-asu service-request-save" type="submit" name="action" value="save_draft">
                                    <i class="bi bi-save" aria-hidden="true"></i> Save as Draft
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-asu service-request-submit" type="submit" name="action" value="<?= $revisionId ? 'edit_resubmit' : 'submit_request'; ?>" id="submitServiceRequest">
                                <i class="bi <?= $revisionId ? 'bi-arrow-repeat' : 'bi-send'; ?>" aria-hidden="true"></i>
                                <?= $revisionId ? 'Edit and Resubmit' : 'Submit Request'; ?>
                            </button>
                        </div>
                    </div>
                </form>
                <script>
                (() => {
                    const formEl = document.querySelector('form.service-request-form-card');
                    const modeEl = document.getElementById('serviceRequestMode');
                    const switchButtons = document.querySelectorAll('[data-request-mode]');
                    const officialPanel = document.getElementById('serviceOfficialServices');
                    const letterPanel = document.getElementById('serviceRequestLetterPanel');
                    const linkEl = document.getElementById('request_letter_url');
                    const eyeEl = document.getElementById('serviceRequestLetterEye');
                    const hintEl = document.getElementById('serviceLetterHint');
                    const submitEl = document.getElementById('submitServiceRequest');
                    const otherBox = document.querySelector('input[name="requested_services[]"][value="Others"]');
                    const otherBlock = document.getElementById('servicePaperOtherBlock');
                    const otherInput = document.getElementById('other_service_details');
                    if (!formEl || !modeEl || !linkEl || !eyeEl || !submitEl) return;
                    let viewedLink = '';
                    let letterError = '';
                    const isHttps = value => {
                        try {
                            const u = new URL(value.trim());
                            return u.protocol === 'https:' && Boolean(u.hostname) && !u.username && !u.password;
                        } catch (_) { return false; }
                    };
                    function syncOther() {
                        const visible = modeEl.value === 'official' && otherBox && otherBox.checked;
                        if (otherBlock) otherBlock.hidden = !visible;
                        if (otherInput) otherInput.required = Boolean(visible);
                    }
                    function syncMode() {
                        const letter = modeEl.value === 'letter';
                        officialPanel.hidden = letter;
                        letterPanel.hidden = !letter;
                        linkEl.required = letter;
                        switchButtons.forEach(btn => btn.setAttribute('aria-pressed', btn.dataset.requestMode === modeEl.value ? 'true' : 'false'));
                        syncOther();
                        syncLetter();
                    }
                    function syncLetter() {
                        const valid = isHttps(linkEl.value);
                        eyeEl.disabled = !valid || modeEl.value !== 'letter';
                        submitEl.disabled = modeEl.value === 'letter' && (viewedLink !== linkEl.value.trim() || !valid);
                        hintEl.textContent = letterError || (modeEl.value === 'letter' && viewedLink === linkEl.value.trim() && valid
                            ? 'Request letter link opened. You can now submit your request.'
                            : 'You must open the link using the eye button before submitting. HTTPS links only.');
                    }
                    switchButtons.forEach(btn => btn.addEventListener('click', () => {
                        modeEl.value = btn.dataset.requestMode;
                        letterError = '';
                        syncMode();
                    }));
                    if (otherBox) otherBox.addEventListener('change', syncOther);
                    linkEl.addEventListener('input', () => { viewedLink = ''; letterError = ''; syncLetter(); });
                    // Native formtarget=_blank opens the document from the server's
                    // validated 303 redirect. Never parse an HTML page as JSON.
                    eyeEl.addEventListener('click', () => {
                        const url = linkEl.value.trim();
                        if (modeEl.value !== 'letter' || !isHttps(url)) return;
                        viewedLink = url;
                        letterError = '';
                        syncLetter();
                    });
                    syncMode();
                })();
                </script>
            <?php elseif ($isOffice): ?>
                <style>
                    .eso-service-card{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;box-shadow:0 10px 28px rgba(0,79,34,.05);overflow:hidden}
                    .eso-service-toolbar{padding:16px 18px;border-bottom:1px solid #edf0ed}
                    .eso-service-table-wrap{overflow-x:auto}
                    .eso-service-table{width:100%;min-width:760px;border-collapse:collapse}
                    .eso-service-table th,.eso-service-table td{padding:13px 14px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
                    .eso-service-table th{background:#f7faf7;color:#455349;font-size:.76rem;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
                    .eso-service-table td{color:#35443a;font-size:.88rem}
                    .eso-service-table td:nth-child(1),
                    .eso-service-table td:nth-child(2),
                    .eso-service-table td:nth-child(3),
                    .eso-service-table td:nth-child(4){text-align:left}
                    .eso-service-table td:nth-child(5),
                    .eso-service-table td:nth-child(6){text-align:center}
                    .eso-service-table tr:last-child td{border-bottom:0}
                    .eso-service-eye-btn{width:38px;min-width:38px;height:36px;min-height:36px;padding:0!important;border:1px solid #cde2d3!important;border-radius:10px!important;background:#fff!important;color:#007a33!important;box-shadow:none!important}
                    .eso-service-eye-btn:hover{background:#edf7f0!important;border-color:#a8d2b4!important;color:#005225!important}
                    .eso-service-details{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;padding:20px;margin-bottom:18px}
                    .eso-service-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 22px}
                    .eso-service-grid label{display:block;color:#718077;font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px}
                    .eso-service-grid div{color:#2e3c33;overflow-wrap:anywhere;white-space:normal}
                    .eso-service-empty{text-align:center;padding:42px 18px;color:#68756c}
                    .eso-service-status{display:inline-block;border-radius:999px;background:#e7f1ff;color:#174b83;padding:5px 10px;font-size:.74rem;font-weight:700;white-space:nowrap}
                    @media(max-width:650px){.eso-service-grid{grid-template-columns:1fr}.eso-service-details{padding:16px}}
                </style>

                <?php if ($officeSuccess !== ''): ?><div class="alert alert-success" role="status"><?= serviceFormEscape($officeSuccess); ?></div><?php endif; ?>
                <?php if ($officeErrors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($officeErrors as $officeError): ?><li><?= serviceFormEscape($officeError); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

                <?php if ($officeRequestDetails): ?>
                    <?php $officeDetailStatus = (string)$officeRequestDetails['status']; ?>
                    <?php $officeDisplayStatus = ($section === 'completed_services' && in_array($officeDetailStatus, ['Scheduled', 'Ongoing'], true) && !empty($officeRequestDetails['extensionist_completed_at'])) ? 'Awaiting ESO Completion' : myServiceStatusLabel($officeDetailStatus); ?>
                    <section class="eso-service-details">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                            <div><h2 class="h6 fw-bold text-success mb-2">Service Request Details</h2><strong><?= serviceFormEscape($officeRequestDetails['service_title'] ?: 'Service Request'); ?></strong><div class="small text-muted mt-1"><?= serviceFormEscape($officeRequestDetails['request_code'] ?: ('SR-' . (int)$officeRequestDetails['request_id'])); ?></div><div class="small text-muted mt-1 rems-service-submission-meta"><?= !empty($officeRequestDetails['submitted_at']) ? 'Submitted' : 'Created'; ?> <?= serviceFormEscape(myServiceFormattedDate($officeRequestDetails['submitted_at'] ?: $officeRequestDetails['created_at'], 'M d, Y h:i A')); ?></div></div>
                            <span class="eso-service-status" data-status="<?= serviceFormEscape($officeDisplayStatus === 'Awaiting ESO Completion' ? $officeDisplayStatus : $officeDetailStatus); ?>"><?= serviceFormEscape($officeDisplayStatus); ?></span>
                        </div>
                        <div class="eso-service-grid">
                            <?php
                                $officePaperServices = servicePaperChoices($officeRequestDetails['requested_services_json'] ?? null);
                                $officeIsLetterRequest = serviceIsLetterRequest($officeRequestDetails);
                                $officeLetterUrl = serviceRequestSafeLetterUrl((string)($officeRequestDetails['request_letter_url'] ?? ''));
                            ?>
                            <?php if (($officeRequestDetails['requested_services_json'] ?? null) !== null): ?>
                                <div><label>Name</label><div><?= serviceFormEscape($officeRequestDetails['contact_person'] ?: '—'); ?></div></div>
                                <div><label>Agency / School / Address</label><div><?= serviceFormEscape($officeRequestDetails['organization_name'] ?: '—'); ?></div></div>
                                <div><label>Date</label><div><?= serviceFormEscape(myServiceFormattedDate($officeRequestDetails['preferred_date'])); ?></div></div>
                                <div><label>Contact Number</label><div><?= serviceFormEscape($officeRequestDetails['contact_number'] ?: '—'); ?></div></div>
                                <div class="rems-service-info-item rems-service-purpose-item"><label>Purpose</label><div><?= serviceFormEscape($officeRequestDetails['service_description'] ?: '—'); ?></div></div>
                                <?php if ($officeIsLetterRequest): ?>
                                <div class="rems-service-info-item">
                                    <label>Request Letter Link</label>
                                    <div><?php if ($officeLetterUrl): ?>
                                        <a class="btn btn-sm btn-outline-asu service-document-view-link" href="<?= serviceFormEscape($officeLetterUrl); ?>" target="_blank" rel="noopener noreferrer" title="View submitted request letter" aria-label="View submitted request letter"><i class="bi bi-eye" aria-hidden="true"></i><span>View Request Letter</span></a>
                                    <?php else: ?>
                                        <span class="small text-muted">No request letter link was saved with this record. Please request the link from the coordinator.</span>
                                    <?php endif; ?></div>
                                </div>
                                <?php else: ?>
                                <div class="rems-service-info-item"><label>Types of Services</label><div><?= serviceFormEscape(implode(', ', $officePaperServices)); ?><?= in_array('Others', $officePaperServices, true) && !empty($officeRequestDetails['other_service_details']) ? ' — ' . serviceFormEscape($officeRequestDetails['other_service_details']) : ''; ?></div></div>
                                <?php endif; ?>
                                <div><label>Full Name</label><div><?= serviceFormEscape($officeRequestDetails['requester_signature_name'] ?: '—'); ?></div></div>
                                <div><label>Assisted by (Full Name)</label><div><?= serviceFormEscape($officeRequestDetails['assisted_by_name'] ?: '—'); ?></div></div>
                            <?php else: ?>
                            <div><label>Type of Service</label><div><?= serviceFormEscape($officeRequestDetails['service_type'] ?: '—'); ?></div></div>
                            <div><label>Organization / Community</label><div><?= serviceFormEscape($officeRequestDetails['organization_name'] ?: '—'); ?></div></div>
                            <div><label>Contact Person</label><div><?= serviceFormEscape($officeRequestDetails['contact_person'] ?: '—'); ?></div></div>
                            <div><label>Contact Details</label><div><?= serviceFormEscape($officeRequestDetails['contact_email'] ?: '—'); ?><?= !empty($officeRequestDetails['contact_number']) ? '<br>' . serviceFormEscape($officeRequestDetails['contact_number']) : ''; ?></div></div>
                            <div><label>Estimated Participants</label><div><?= $officeRequestDetails['target_participants'] !== null ? number_format((int)$officeRequestDetails['target_participants']) : '—'; ?></div></div>
                            <div><label>Preferred Date / Location</label><div><?= serviceFormEscape(myServiceFormattedDate($officeRequestDetails['preferred_date'])); ?> · <?= serviceFormEscape($officeRequestDetails['preferred_location'] ?: '—'); ?></div></div>
                            <div style="grid-column:1/-1"><label>Description</label><div><?= serviceFormEscape($officeRequestDetails['service_description'] ?: '—'); ?></div></div>
                            <?php if ($officeIsLetterRequest || $officeLetterUrl): ?>
                            <div style="grid-column:1/-1"><label>Request Letter Link</label><div><?php if ($officeLetterUrl): ?><a class="btn btn-sm btn-outline-asu service-document-view-link" href="<?= serviceFormEscape($officeLetterUrl); ?>" target="_blank" rel="noopener noreferrer" title="View submitted request letter" aria-label="View submitted request letter"><i class="bi bi-eye" aria-hidden="true"></i><span>View Request Letter</span></a><?php else: ?><span class="small text-muted">No request letter link was saved with this record. Please request the link from the coordinator.</span><?php endif; ?></div></div>
                            <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($officeRequestDetails['office_remarks'])): ?><div style="grid-column:1/-1"><label>ESO Remarks / Information Needed</label><div><?= serviceFormEscape($officeRequestDetails['office_remarks']); ?></div></div><?php endif; ?>
                            <?php if (!empty($officeRequestDetails['assigned_unit']) || !empty($officeRequestDetails['assigned_personnel'])): ?><div><label>Assigned Unit / Personnel</label><div><?= serviceFormEscape($officeRequestDetails['assigned_unit'] ?: '—'); ?> · <?= serviceFormEscape($officeRequestDetails['assigned_personnel'] ?: '—'); ?></div></div><div><label>Confirmed Schedule</label><div><?= serviceFormEscape(myServiceFormattedDate($officeRequestDetails['scheduled_date'] ?? null)); ?><?= !empty($officeRequestDetails['scheduled_time']) ? ' · ' . serviceFormEscape(date('g:i A', strtotime((string)$officeRequestDetails['scheduled_time']))) : ''; ?> · <?= serviceFormEscape($officeRequestDetails['scheduled_location'] ?: '—'); ?></div></div><?php endif; ?>
                        </div>

                        <?php if ($section === 'service_requests' && in_array($officeDetailStatus, ['Submitted', 'Acknowledged', 'Needs Information'], true)): ?>
                            <div class="eso-service-review-controls border-top mt-4 pt-3">
                                <form id="officeInformationForm" method="post" action="<?= BASE_URL; ?>/extension/services.php?section=service_requests" class="eso-service-remarks-form">
                                    <input type="hidden" name="csrf_token" value="<?= serviceFormEscape($officeCsrf); ?>">
                                    <input type="hidden" name="request_id" value="<?= (int)$officeRequestDetails['request_id']; ?>">
                                    <label class="form-label" for="officeRemarks">Information needed / remarks</label>
                                    <textarea class="form-control" id="officeRemarks" name="office_remarks" rows="3" maxlength="3000" placeholder="Describe what the Extensionist needs to provide" required><?= serviceFormEscape($officeRequestDetails['office_remarks'] ?? ''); ?></textarea>
                                </form>
                                <div class="eso-service-review-actions">
                                    <?php if ($officeDetailStatus === 'Submitted'): ?>
                                        <form method="post" action="<?= BASE_URL; ?>/extension/services.php?section=service_requests">
                                            <input type="hidden" name="csrf_token" value="<?= serviceFormEscape($officeCsrf); ?>">
                                            <input type="hidden" name="request_id" value="<?= (int)$officeRequestDetails['request_id']; ?>">
                                            <button type="submit" class="btn btn-asu" name="action" value="acknowledge">Acknowledge Receipt</button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="submit" form="officeInformationForm" class="btn btn-outline-asu" name="action" value="request_information">Request More Information</button>
                                    <a class="btn btn-light border eso-service-back-btn" href="<?= BASE_URL; ?>/extension/services.php?section=<?= serviceFormEscape($section); ?>">Back to list</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($section === 'schedule_assignments' && in_array($officeDetailStatus, ['Acknowledged', 'Assigned'], true)): ?>
                            <form method="post" action="<?= BASE_URL; ?>/extension/services.php?section=schedule_assignments" class="eso-service-schedule-form border-top mt-4 pt-3">
                                <input type="hidden" name="csrf_token" value="<?= serviceFormEscape($officeCsrf); ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$officeRequestDetails['request_id']; ?>">
                                <h3 class="h6 fw-bold mb-3">Assign Personnel and Set Schedule</h3>
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label" for="assignedUnit">Assigned Unit</label><input class="form-control" id="assignedUnit" name="assigned_unit" maxlength="180" value="<?= serviceFormEscape($officeRequestDetails['assigned_unit'] ?? ''); ?>" required></div>
                                    <div class="col-md-6"><label class="form-label" for="assignedPersonnel">Assigned Personnel</label><input class="form-control" id="assignedPersonnel" name="assigned_personnel" maxlength="180" value="<?= serviceFormEscape($officeRequestDetails['assigned_personnel'] ?? ''); ?>" required></div>
                                    <div class="col-md-4"><label class="form-label" for="scheduledDate">Schedule Date</label><input class="form-control" type="date" id="scheduledDate" name="scheduled_date" value="<?= serviceFormEscape($officeRequestDetails['scheduled_date'] ?? ''); ?>" required></div>
                                    <div class="col-md-4"><label class="form-label" for="scheduledTime">Schedule Time</label><input class="form-control" type="time" id="scheduledTime" name="scheduled_time" value="<?= !empty($officeRequestDetails['scheduled_time']) ? serviceFormEscape(substr((string)$officeRequestDetails['scheduled_time'], 0, 5)) : ''; ?>" required></div>
                                    <div class="col-md-4"><label class="form-label" for="scheduledLocation">Service Location</label><input class="form-control" id="scheduledLocation" name="scheduled_location" maxlength="255" value="<?= serviceFormEscape($officeRequestDetails['scheduled_location'] ?? ''); ?>" required></div>
                                </div>
                                <div class="eso-service-detail-actions mt-3">
                                    <button type="submit" class="btn btn-asu" name="action" value="save_schedule">Save Assignment &amp; Schedule</button>
                                    <a class="btn btn-light border eso-service-back-btn" href="<?= BASE_URL; ?>/extension/services.php?section=<?= serviceFormEscape($section); ?>">Back to list</a>
                                </div>
                            </form>
                        <?php elseif ($section === 'schedule_assignments' && $officeDetailStatus === 'Scheduled'): ?>
                            <div class="eso-service-detail-actions border-top mt-4 pt-3">
                                <form method="post" action="<?= BASE_URL; ?>/extension/services.php?section=schedule_assignments">
                                    <input type="hidden" name="csrf_token" value="<?= serviceFormEscape($officeCsrf); ?>">
                                    <input type="hidden" name="request_id" value="<?= (int)$officeRequestDetails['request_id']; ?>">
                                    <button type="submit" class="btn btn-asu" name="action" value="start_service">Mark Service as Ongoing</button>
                                </form>
                                <a class="btn btn-light border eso-service-back-btn" href="<?= BASE_URL; ?>/extension/services.php?section=<?= serviceFormEscape($section); ?>">Back to list</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($section === 'completed_services' && in_array($officeDetailStatus, ['Scheduled', 'Ongoing'], true) && !empty($officeRequestDetails['extensionist_completed_at'])): ?>
                            <div class="border-top mt-4 pt-3">
                                <p class="small text-muted mb-2">The Extensionist confirmed that the service has been completed. ESO can now finalize the record.</p>
                                <div class="eso-service-detail-actions">
                                    <form method="post" action="<?= BASE_URL; ?>/extension/services.php?section=completed_services">
                                        <input type="hidden" name="csrf_token" value="<?= serviceFormEscape($officeCsrf); ?>">
                                        <input type="hidden" name="request_id" value="<?= (int)$officeRequestDetails['request_id']; ?>">
                                        <button type="submit" class="btn btn-asu" name="action" value="mark_completed">Mark as Completed</button>
                                    </form>
                                    <a class="btn btn-light border eso-service-back-btn" href="<?= BASE_URL; ?>/extension/services.php?section=<?= serviceFormEscape($section); ?>">Back to list</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Other detail states have only a Back button; the stages above
                        // already include Back beside their corresponding action.
                        $hasScheduleActions = $section === 'schedule_assignments' && in_array($officeDetailStatus, ['Acknowledged', 'Assigned', 'Scheduled'], true);
                        $hasCompletionActions = $section === 'completed_services' && in_array($officeDetailStatus, ['Scheduled', 'Ongoing'], true) && !empty($officeRequestDetails['extensionist_completed_at']);
                        $hasReviewActions = $section === 'service_requests' && in_array($officeDetailStatus, ['Submitted', 'Acknowledged', 'Needs Information'], true);
                        ?>
                        <?php if (!$hasScheduleActions && !$hasCompletionActions && !$hasReviewActions): ?>
                            <div class="eso-service-detail-actions border-top mt-4 pt-3">
                                <a class="btn btn-light border eso-service-back-btn" href="<?= BASE_URL; ?>/extension/services.php?section=<?= serviceFormEscape($section); ?>">Back to list</a>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php elseif ($officeSelectedRequest > 0): ?>
                    <div class="alert alert-danger">The selected service request could not be found.</div>
                <?php endif; ?>

                <?php // The list and filters belong to the listing page, not an open request detail. ?>
                <?php if ($officeSelectedRequest <= 0): ?>
                <section class="eso-service-card">
                    <div class="eso-service-toolbar">
                        <form class="eso-service-filters" method="get" action="<?= BASE_URL; ?>/extension/services.php">
                            <input type="hidden" name="section" value="<?= serviceFormEscape($section); ?>">
                            <div class="eso-service-filter-search">
                                <label class="form-label small fw-semibold" for="esoServiceSearch">Search requests</label>
                                <div class="eso-service-search-field">
                                    <i class="bi bi-search" aria-hidden="true"></i>
                                    <input class="form-control" type="search" id="esoServiceSearch" name="q" value="<?= serviceFormEscape($officeSearch); ?>" placeholder="Request number, title, organization">
                                </div>
                            </div>
                            <div class="eso-service-filter-status">
                                <label class="form-label small fw-semibold" for="esoServiceStatus">Status</label>
                                <select class="form-select" id="esoServiceStatus" name="status">
                                    <option value="">All relevant statuses</option>
                                    <?php foreach ($serviceStatuses as $statusOption): ?>
                                        <option value="<?= serviceFormEscape($statusOption); ?>" <?= $officeFilterStatus === $statusOption ? 'selected' : ''; ?>><?= serviceFormEscape(myServiceStatusLabel($statusOption)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="eso-service-filter-actions">
                                <button class="btn btn-asu eso-service-filter-btn" type="submit"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Filter</button>
                                <a class="btn btn-outline-asu eso-service-reset-btn" href="<?= BASE_URL; ?>/extension/services.php?section=<?= serviceFormEscape($section); ?>">Reset</a>
                            </div>
                        </form>
                    </div>
                    <?php if (!$officeRows): ?><div class="eso-service-empty"><i class="bi bi-inbox fs-2 d-block mb-2" aria-hidden="true"></i><strong>No service requests in this section.</strong><div class="small mt-2">Requests will appear here as their status changes.</div></div><?php else: ?>
                        <div class="eso-service-table-wrap"><table class="eso-service-table"><thead><tr><th>Request</th><th>Organization</th><th>Service Type</th><th>Schedule</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($officeRows as $officeRow): $rowId = (int)$officeRow['request_id']; $rowStatus = (string)$officeRow['status']; $rowDisplayStatus = ($section === 'completed_services' && in_array($rowStatus, ['Scheduled', 'Ongoing'], true) && !empty($officeRow['extensionist_completed_at'])) ? 'Awaiting ESO Completion' : myServiceStatusLabel($rowStatus); ?><tr><td><strong><?= serviceFormEscape($officeRow['service_title'] ?: 'Service Request'); ?></strong><span class="d-block small text-muted"><?= serviceFormEscape($officeRow['request_code'] ?: ('SR-' . $rowId)); ?></span></td><td><?= serviceFormEscape($officeRow['organization_name'] ?: '—'); ?></td><td><?= serviceFormEscape($officeRow['service_type'] ?: '—'); ?></td><td><?= !empty($officeRow['scheduled_date']) ? serviceFormEscape(myServiceFormattedDate($officeRow['scheduled_date'])) : serviceFormEscape(myServiceFormattedDate($officeRow['preferred_date'])); ?><?= !empty($officeRow['scheduled_time']) ? ' · ' . serviceFormEscape(date('g:i A', strtotime((string)$officeRow['scheduled_time']))) : ''; ?></td><td><span class="eso-service-status" data-status="<?= serviceFormEscape($rowDisplayStatus === 'Awaiting ESO Completion' ? $rowDisplayStatus : $rowStatus); ?>"><?= serviceFormEscape($rowDisplayStatus); ?></span></td><td><a class="btn btn-sm eso-service-eye-btn" href="<?= BASE_URL; ?>/extension/services.php?section=<?= serviceFormEscape($section); ?>&amp;view=<?= $rowId; ?>" title="View / Process" aria-label="View / Process"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View / Process</span></a></td></tr><?php endforeach; ?></tbody></table></div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            <?php else: ?>
                <style>
                    .my-sr-card{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;box-shadow:0 10px 28px rgba(0,79,34,.05);overflow:hidden}
                    .my-sr-toolbar{padding:17px 18px;border-bottom:1px solid #edf0ed}
                    .my-sr-filters{display:flex;align-items:end;gap:10px;flex-wrap:wrap}
                    .my-sr-filters .form-control{min-width:230px}
                    .my-sr-table-wrap{overflow-x:auto}
                    .my-sr-table{width:100%;min-width:760px;border-collapse:collapse}
                    .my-sr-table th,.my-sr-table td{padding:13px 14px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
                    .my-sr-table th{background:#f7faf7;color:#455349;font-size:.76rem;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
                    .my-sr-table td{color:#35443a;font-size:.88rem}
                    .my-sr-table tr:last-child td{border-bottom:0}
                    .my-sr-title{font-weight:700;color:#203c2a}
                    .my-sr-code{display:block;color:#758078;font-size:.76rem;margin-top:3px}
                    .my-sr-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:.74rem;font-weight:700;white-space:nowrap}
                    .my-sr-status-draft{background:#eef1ef;color:#4b5750}
                    .my-sr-status-pending{background:#fff3c4;color:#664f00}
                    .my-sr-status-processing{background:#e7f1ff;color:#174b83}
                    .my-sr-status-ongoing{background:#e2f3e8;color:#176236}
                    .my-sr-status-completed{background:#08752c;color:#fff}
                    .my-sr-status-neutral{background:#f0f2f1;color:#4b5750}
                    .my-sr-empty{text-align:center;padding:46px 18px;color:#68756c}
                    .my-sr-empty i{display:block;font-size:2rem;color:#6d9a7b;margin-bottom:10px}
                    .my-sr-details{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;padding:20px;margin-bottom:18px}
                    .my-sr-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px 22px}
                    .my-sr-detail label{display:block;color:#718077;font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px}
                    .my-sr-detail div{color:#2e3c33;overflow-wrap:anywhere}
                    .my-sr-description{white-space:pre-wrap}
                    .my-sr-foot{padding:13px 17px;border-top:1px solid #edf0ed;color:#68756c;font-size:.8rem}
                    @media(max-width:600px){.my-sr-detail-grid{grid-template-columns:1fr}.my-sr-details{padding:16px}}
                </style>

                <?php if ($myViewId > 0 && !$myViewRequest): ?>
                    <div class="alert alert-danger" role="alert">The service request could not be found in your account.</div>
                <?php endif; ?>
                <?php if ($section === 'my_service_requests' && $success !== ''): ?><div class="alert alert-success" role="status"><?= serviceFormEscape($success); ?></div><?php endif; ?>
                <?php if ($section === 'my_service_requests' && $myRequestErrors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($myRequestErrors as $myRequestError): ?><li><?= serviceFormEscape($myRequestError); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

                <?php if ($myViewRequest): ?>
                    <?php $viewLetterUrl = myServiceSafeHttpsUrl($myViewRequest['request_letter_url'] ?? null); ?>
                    <section class="my-sr-details" aria-labelledby="myServiceDetailsTitle">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                            <div>
                                <h2 class="h6 fw-bold text-success mb-2" id="myServiceDetailsTitle">Request Details</h2>
                                <div class="my-sr-title"><?= serviceFormEscape($myViewRequest['service_title'] ?: 'Service Request'); ?></div>
                                <span class="my-sr-code"><?= serviceFormEscape($myViewRequest['request_code'] ?: ('SR-' . (int)$myViewRequest['request_id'])); ?></span>
                                <span class="my-sr-code rems-service-submission-meta"><?= !empty($myViewRequest['submitted_at']) ? 'Submitted' : 'Created'; ?> <?= serviceFormEscape(myServiceFormattedDate($myViewRequest['submitted_at'] ?: $myViewRequest['created_at'], 'M d, Y h:i A')); ?></span>
                            </div>
                            <span class="my-sr-badge <?= myServiceStatusBadge((string)$myViewRequest['status']); ?>" data-status="<?= serviceFormEscape((string)$myViewRequest['status']); ?>"><?= serviceFormEscape(myServiceStatusLabel((string)$myViewRequest['status'])); ?></span>
                        </div>
                        <div class="my-sr-detail-grid">
                            <?php
                                $myPaperServices = servicePaperChoices($myViewRequest['requested_services_json'] ?? null);
                                $myIsLetterRequest = serviceIsLetterRequest($myViewRequest);
                            ?>
                            <?php if (($myViewRequest['requested_services_json'] ?? null) !== null): ?>
                                <div class="my-sr-detail"><label>Name</label><div><?= serviceFormEscape($myViewRequest['contact_person'] ?: '—'); ?></div></div>
                                <div class="my-sr-detail"><label>Agency / School / Address</label><div><?= serviceFormEscape($myViewRequest['organization_name'] ?: '—'); ?></div></div>
                                <div class="my-sr-detail"><label>Date</label><div><?= serviceFormEscape(myServiceFormattedDate($myViewRequest['preferred_date'])); ?></div></div>
                                <div class="my-sr-detail"><label>Contact Number</label><div><?= serviceFormEscape($myViewRequest['contact_number'] ?: '—'); ?></div></div>
                                <div class="my-sr-detail"><label>Purpose</label><div class="my-sr-description"><?= serviceFormEscape($myViewRequest['service_description'] ?: '—'); ?></div></div>
                                <?php if ($myIsLetterRequest): ?>
                                <div class="my-sr-detail"><label>Request Letter Link</label><div><?php if ($viewLetterUrl): ?><a class="btn btn-sm btn-outline-asu service-document-view-link" href="<?= serviceFormEscape($viewLetterUrl); ?>" target="_blank" rel="noopener noreferrer" title="View request letter" aria-label="View request letter"><i class="bi bi-eye" aria-hidden="true"></i><span>View Request Letter</span></a><?php else: ?><span class="small text-muted">No request letter link was saved with this record. Please update the link if ESO requests more information.</span><?php endif; ?></div></div>
                                <?php else: ?>
                                <div class="my-sr-detail"><label>Types of Services</label><div><?= serviceFormEscape(implode(', ', $myPaperServices)); ?><?= in_array('Others', $myPaperServices, true) && !empty($myViewRequest['other_service_details']) ? ' — ' . serviceFormEscape($myViewRequest['other_service_details']) : ''; ?></div></div>
                                <?php endif; ?>
                                <div class="my-sr-detail"><label>Full Name</label><div><?= serviceFormEscape($myViewRequest['requester_signature_name'] ?: '—'); ?></div></div>
                                <div class="my-sr-detail"><label>Assisted by (Full Name)</label><div><?= serviceFormEscape($myViewRequest['assisted_by_name'] ?: '—'); ?></div></div>

                            <?php else: ?>
                            <div class="my-sr-detail"><label>Service Type</label><div><?= serviceFormEscape($myViewRequest['service_type'] ?: '—'); ?></div></div>
                            <div class="my-sr-detail"><label>Requesting Organization / Community</label><div><?= serviceFormEscape($myViewRequest['organization_name'] ?: '—'); ?></div></div>
                            <div class="my-sr-detail"><label>Contact Person</label><div><?= serviceFormEscape($myViewRequest['contact_person'] ?: '—'); ?></div></div>
                            <div class="my-sr-detail"><label>Contact Details</label><div><?= serviceFormEscape($myViewRequest['contact_email'] ?: '—'); ?><?= !empty($myViewRequest['contact_number']) ? '<br>' . serviceFormEscape($myViewRequest['contact_number']) : ''; ?></div></div>
                            <div class="my-sr-detail"><label>Estimated Participants</label><div><?= $myViewRequest['target_participants'] !== null ? number_format((int)$myViewRequest['target_participants']) : '—'; ?></div></div>

                            <div class="my-sr-detail"><label>Preferred Date</label><div><?= serviceFormEscape(myServiceFormattedDate($myViewRequest['preferred_date'])); ?></div></div>
                            <div class="my-sr-detail"><label>Preferred Location</label><div><?= serviceFormEscape($myViewRequest['preferred_location'] ?: '—'); ?></div></div>
                            <div class="my-sr-detail" style="grid-column:1/-1"><label>Description</label><div class="my-sr-description"><?= serviceFormEscape($myViewRequest['service_description'] ?: '—'); ?></div></div>
                            <?php endif; ?>
                            <?php if (!empty($myViewRequest['office_remarks'])): ?><div class="my-sr-detail" style="grid-column:1/-1"><label>Additional Information Requested by ESO</label><div class="my-sr-description"><?= serviceFormEscape($myViewRequest['office_remarks']); ?></div></div><?php endif; ?>
                            <?php if (!empty($myViewRequest['assigned_unit']) || !empty($myViewRequest['scheduled_date'])): ?><div class="my-sr-detail"><label>Assigned Unit / Personnel</label><div><?= serviceFormEscape($myViewRequest['assigned_unit'] ?: '—'); ?> · <?= serviceFormEscape($myViewRequest['assigned_personnel'] ?: '—'); ?></div></div><div class="my-sr-detail"><label>Confirmed Schedule / Location</label><div><?= serviceFormEscape(myServiceFormattedDate($myViewRequest['scheduled_date'] ?? null)); ?><?= !empty($myViewRequest['scheduled_time']) ? ' · ' . serviceFormEscape(date('g:i A', strtotime((string)$myViewRequest['scheduled_time']))) : ''; ?><br><?= serviceFormEscape($myViewRequest['scheduled_location'] ?: '—'); ?></div></div><?php endif; ?>
                            <?php if (($myViewRequest['requested_services_json'] ?? null) === null && ($myIsLetterRequest || $viewLetterUrl)): ?>
                            <div class="my-sr-detail" style="grid-column:1/-1"><label>Request Letter Link</label><div><?php if ($viewLetterUrl): ?><a class="btn btn-sm btn-outline-asu service-document-view-link" href="<?= serviceFormEscape($viewLetterUrl); ?>" target="_blank" rel="noopener noreferrer" title="View request letter" aria-label="View request letter"><i class="bi bi-eye" aria-hidden="true"></i><span>View Request Letter</span></a><?php else: ?><span class="small text-muted">No request letter link was saved with this record.</span><?php endif; ?></div></div>
                            <?php endif; ?>
                        </div>
                        <div class="my-sr-detail-actions">
                            <?php if ((string)$myViewRequest['status'] === 'Needs Information'): ?>
                                <a class="btn btn-asu" href="<?= BASE_URL; ?>/extension/services.php?section=request_service&amp;revise=<?= (int)$myViewRequest['request_id']; ?>">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit and Resubmit
                                </a>
                            <?php endif; ?>
                            <?php if (in_array((string)$myViewRequest['status'], ['Scheduled', 'Ongoing'], true) && empty($myViewRequest['extensionist_completed_at'])): ?>
                                <form method="post" action="<?= BASE_URL; ?>/extension/services.php?section=my_service_requests">
                                    <input type="hidden" name="csrf_token" value="<?= serviceFormEscape((string)$_SESSION['service_request_csrf']); ?>">
                                    <input type="hidden" name="request_id" value="<?= (int)$myViewRequest['request_id']; ?>">
                                    <button class="btn btn-asu" name="action" value="confirm_service_completed">Confirm Service Completed</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-light border my-sr-back-to-list" href="<?= BASE_URL; ?>/extension/services.php?section=my_service_requests">Back to My Service Requests</a>
                        </div>
                    </section>
                <?php endif; ?>

                <?php // Hide listing filters and records while a request detail is open. ?>
                <?php if ($myViewId <= 0): ?>
                <!-- Compact filters matching Research Records; no statistic cards. -->
                <section class="my-sr-filter-card" aria-label="Filter service requests">
                    <form class="my-sr-filters" method="get" action="<?= BASE_URL; ?>/extension/services.php">
                        <input type="hidden" name="section" value="my_service_requests">
                        <div class="my-sr-filter-search">
                            <label class="form-label" for="myServiceSearch">Search</label>
                            <div class="my-sr-search-field">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input class="form-control" type="search" id="myServiceSearch" name="q" value="<?= serviceFormEscape($mySearchTerm); ?>" placeholder="Search request, code, or organization">
                            </div>
                        </div>
                        <div class="my-sr-filter-status">
                            <label class="form-label" for="myServiceStatus">Status</label>
                            <select class="form-select" id="myServiceStatus" name="status">
                                <option value="">All Statuses</option>
                                <?php foreach ($serviceStatuses as $statusOption): ?>
                                    <option value="<?= serviceFormEscape($statusOption); ?>" <?= $mySelectedStatus === $statusOption ? 'selected' : ''; ?>><?= serviceFormEscape(myServiceStatusLabel($statusOption)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="my-sr-filter-actions">
                            <button class="btn btn-asu my-sr-filter-submit" type="submit"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Filter</button>
                            <a class="btn btn-outline-asu my-sr-filter-reset" href="<?= BASE_URL; ?>/extension/services.php?section=my_service_requests">Reset</a>
                        </div>
                    </form>
                </section>

                <section class="my-sr-card" aria-label="Your service requests">
                    <div class="my-sr-records-head">
                        <div class="my-sr-head-title">
                            <span class="my-sr-head-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
                            <h2>Service Request Records</h2>
                        </div>
                        <span class="my-sr-record-count"><?= number_format(count($myRequests)); ?> record<?= count($myRequests) === 1 ? '' : 's'; ?></span>
                    </div>

                    <?php if (!$myRequests): ?>
                        <div class="my-sr-empty">
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <strong>No service requests found.</strong>
                            <div class="mt-2">Submit a training or technical service request to see it here.</div>
                            <a class="btn btn-asu mt-3" href="<?= BASE_URL; ?>/extension/services.php?section=request_service"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Request a Service</a>
                        </div>
                    <?php else: ?>
                        <div class="my-sr-table-wrap">
                            <table class="my-sr-table">
                                <colgroup>
                                    <col class="my-sr-col-request"><col class="my-sr-col-service"><col class="my-sr-col-date"><col class="my-sr-col-status"><col class="my-sr-col-action">
                                </colgroup>
                                <thead><tr><th scope="col">Request</th><th scope="col">Service Type</th><th scope="col">Date Submitted</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                                <tbody>
                                <?php foreach ($myRequests as $request): ?>
                                    <?php
                                        $requestId = (int)$request['request_id'];
                                        $requestStatus = (string)$request['status'];
                                        $requestCode = (string)($request['request_code'] ?: ('SR-' . $requestId));
                                    ?>
                                    <tr>
                                        <td><span class="my-sr-title"><?= serviceFormEscape($request['service_title'] ?: 'Service Request'); ?></span><span class="my-sr-code"><?= serviceFormEscape($requestCode); ?> · <?= serviceFormEscape($request['organization_name'] ?: 'No organization entered'); ?></span></td>
                                        <td><?= serviceFormEscape($request['service_type'] ?: '—'); ?></td>
                                        <td><?= serviceFormEscape(myServiceFormattedDate($request['submitted_at'] ?: $request['created_at'])); ?></td>
                                        <td><span class="my-sr-badge <?= myServiceStatusBadge($requestStatus); ?>" data-status="<?= serviceFormEscape($requestStatus); ?>"><?= serviceFormEscape(myServiceStatusLabel($requestStatus)); ?></span></td>
                                        <td>
                                            <div class="my-sr-row-actions">
                                                <?php if ($requestStatus === 'Draft'): ?><a class="btn btn-sm btn-outline-asu" href="<?= BASE_URL; ?>/extension/services.php?section=request_service&amp;draft=<?= $requestId; ?>">Continue Draft</a><?php endif; ?>
                                                <a class="btn btn-sm my-sr-eye-btn" href="<?= BASE_URL; ?>/extension/services.php?section=my_service_requests&amp;view=<?= $requestId; ?>" title="View request details" aria-label="View request details"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View Details</span></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; // End listing-only controls. ?>
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
/* STATUS PILLS — yellow for processing, green for completion */
.rems-view-shell .monitoring-status,
.rems-view-shell .eso-monitor-status,
.rems-view-shell .monitor-flow-status,
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
.rems-view-shell .eso-service-toolbar .eso-service-filters {
    display:grid;
    grid-template-columns:minmax(300px,1.8fr) minmax(220px,1fr) auto;
    align-items:end;
    gap:14px;
    width:100%;
}
.rems-view-shell .eso-service-toolbar .eso-service-filter-search,
.rems-view-shell .eso-service-toolbar .eso-service-filter-status {min-width:0;}
.rems-view-shell .eso-service-toolbar .eso-service-filter-search .form-control,
.rems-view-shell .eso-service-toolbar .eso-service-filter-status .form-select {width:100%;height:44px;min-height:44px!important;}
.rems-view-shell .eso-service-toolbar .eso-service-filter-actions {display:flex;align-items:center;gap:10px;justify-content:flex-start;}
.rems-view-shell .eso-service-toolbar .eso-service-filter-actions .btn {min-width:100px;height:44px;min-height:44px;white-space:nowrap;}
.rems-view-shell .eso-service-toolbar .eso-service-filter-btn {background:#006b30!important;border:1px solid #006b30!important;color:#fff!important;box-shadow:none!important;}
.rems-view-shell .eso-service-toolbar .eso-service-filter-btn:hover {background:#005225!important;border-color:#005225!important;color:#fff!important;}
.rems-view-shell .eso-service-toolbar .eso-service-reset-btn {background:#fff!important;border:1px solid #cde2d3!important;color:#006b30!important;box-shadow:none!important;}
.rems-view-shell .eso-service-toolbar .eso-service-reset-btn:hover {background:#f2f8f3!important;border-color:#a8d2b4!important;color:#005225!important;}
.rems-view-shell .eso-service-table th:nth-child(1),
.rems-view-shell .eso-service-table th:nth-child(2),
.rems-view-shell .eso-service-table th:nth-child(3),
.rems-view-shell .eso-service-table th:nth-child(4) {text-align:left!important;}
.rems-view-shell .eso-service-table th:nth-child(5),
.rems-view-shell .eso-service-table th:nth-child(6) {text-align:center!important;}
.rems-view-shell .eso-service-table td:nth-child(5),
.rems-view-shell .eso-service-table td:nth-child(6) {text-align:center!important;}
.rems-view-shell .eso-service-table td:nth-child(4) {white-space:nowrap;}
.rems-view-shell .eso-service-table td:nth-child(6) .btn {margin-inline:auto;}
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
/* Service request form actions — exact typography and button treatment from
   extension/create.php. Scoped to this form so ESO controls are untouched. */
.rems-view-shell .service-request-actions {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    flex-wrap: wrap;
    gap: 9px !important;
    margin-top: 24px !important;
    padding-top: 17px !important;
    border-top: 1px solid rgba(0, 122, 51, .08) !important;
}
.rems-view-shell .service-request-actions .btn {
    display: inline-flex !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 6px !important;
    min-height: 42px !important;
    padding: 0 14px !important;
    border-radius: 10px !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    line-height: 1.25 !important;
    letter-spacing: 0 !important;
    white-space: nowrap;
    box-shadow: none !important;
    transform: none !important;
    transition: none !important;
}
.rems-view-shell .service-request-actions .btn i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px !important;
    line-height: 1 !important;
}
.rems-view-shell .service-request-actions .btn-outline-asu {
    background: #fff !important;
    color: #007a33 !important;
    border: 1px solid rgba(0,122,51,.20) !important;
}
.rems-view-shell .service-request-actions .btn-outline-asu:hover {
    background: #f2f8f3 !important;
    color: #005c26 !important;
    border-color: rgba(0,122,51,.28) !important;
}
.rems-view-shell .service-request-actions .btn-asu {
    background: linear-gradient(135deg,#007a33,#005c26) !important;
    color: #fff !important;
    border: 1px solid #007a33 !important;
    box-shadow: 0 8px 18px rgba(0,122,51,.14) !important;
}
.rems-view-shell .service-request-actions .btn-asu:hover {
    background: #005c26 !important;
    border-color: #005c26 !important;
    color: #fff !important;
}
.rems-view-shell .service-request-actions .btn:disabled {
    opacity: .55 !important;
    cursor: not-allowed !important;
    box-shadow: none !important;
}
@media (max-width: 560px) {
    .rems-view-shell .service-request-actions {
        flex-direction: column;
        align-items: stretch !important;
    }
    .rems-view-shell .service-request-actions .btn { width: 100%; }
}
/* Top navigation actions — same yellow/gold treatment as New Proposal. */
.rems-view-shell .rems-view-heading .rems-header-back.rems-top-yellow-btn,
.rems-view-shell .service-request-form-card a.rems-top-yellow-btn {
    background: #ffd51f !important;
    border: 1px solid #ffd51f !important;
    color: #003f1a !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-weight: 800 !important;
    box-shadow: none !important;
    text-decoration: none !important;
    transform: none !important;
    transition: none !important;
}
.rems-view-shell .rems-view-heading .rems-header-back.rems-top-yellow-btn:hover,
.rems-view-shell .service-request-form-card a.rems-top-yellow-btn:hover {
    background: #f0bd00 !important;
    border-color: #f0bd00 !important;
    color: #003f1a !important;
    box-shadow: none !important;
    transform: none !important;
}
.rems-view-shell .rems-top-yellow-btn i {
    color: inherit !important;
}
.rems-view-shell .rems-top-yellow-btn:focus-visible {
    outline: 3px solid rgba(255, 213, 31, .42) !important;
    outline-offset: 3px;
}
/* My Service Requests: white secondary button (yellow remains for Back to Extension). */
.rems-view-shell .service-request-form-card a.rems-top-white-btn {
    background: #ffffff !important;
    border: 1px solid #c9dfcf !important;
    color: #007a33 !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-weight: 800 !important;
    box-shadow: none !important;
    text-decoration: none !important;
    transform: none !important;
    transition: none !important;
}
.rems-view-shell .service-request-form-card a.rems-top-white-btn:hover {
    background: #f3f8f4 !important;
    border-color: #a8cdb3 !important;
    color: #005c26 !important;
    box-shadow: none !important;
}
.rems-view-shell .service-request-form-card a.rems-top-white-btn i {
    color: inherit !important;
}
.rems-view-shell .service-request-form-card a.rems-top-white-btn:focus-visible {
    outline: 3px solid rgba(0,122,51,.20) !important;
    outline-offset: 3px;
}

/* My Service Requests — streamlined Research Records style.
   Layout only: preserves filtering, viewing, drafts, resubmission and completion. */
.rems-view-shell .my-sr-filter-card {
    background: #fff;
    border: 1px solid rgba(0,107,46,.12);
    border-radius: 18px;
    box-shadow: 0 14px 34px rgba(16,24,16,.045);
    padding: 18px 20px;
    margin: 0 0 18px;
}
.rems-view-shell .my-sr-filter-card .my-sr-filters {
    display: grid;
    grid-template-columns: minmax(220px, 1.8fr) minmax(175px, 1fr) auto;
    align-items: end;
    gap: 14px;
    width: 100%;
}
.rems-view-shell .my-sr-filter-card .my-sr-filter-search,
.rems-view-shell .my-sr-filter-card .my-sr-filter-status { min-width: 0; }
.rems-view-shell .my-sr-filter-card .form-label {
    display: block;
    margin: 0 0 7px !important;
    color: #5a675d !important;
    font: 700 10px/1.4 "Century Gothic", Arial, sans-serif !important;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.rems-view-shell .my-sr-filter-card .my-sr-search-field { position: relative; }
.rems-view-shell .my-sr-filter-card .my-sr-search-field > i {
    position: absolute;
    top: 50%; left: 15px;
    transform: translateY(-50%);
    z-index: 2;
    color: #658071;
    font-size: 14px;
    pointer-events: none;
}
.rems-view-shell .my-sr-filter-card .my-sr-search-field > .form-control { padding-left: 40px !important; min-width:0; }
.rems-view-shell .my-sr-filter-card .form-control,
.rems-view-shell .my-sr-filter-card .form-select { width: 100%; height: 44px; min-height:44px !important; background-color:#fff; }
.rems-view-shell .my-sr-filter-card .my-sr-filter-actions {
    display: flex;
    align-items: center;
    gap: 9px;
}
.rems-view-shell .my-sr-filter-card .my-sr-filter-actions .btn {
    min-width: 95px;
    height: 44px;
    min-height: 44px;
    margin: 0;
    white-space: nowrap;
    font: 800 11px/1.2 "Century Gothic", Arial, sans-serif !important;
}
.rems-view-shell .my-sr-filter-card .my-sr-filter-submit {
    color: #fff !important;
    background: #006b30 !important;
    border: 1px solid #006b30 !important;
    box-shadow: none !important;
}
.rems-view-shell .my-sr-filter-card .my-sr-filter-submit:hover { background:#005225 !important; border-color:#005225 !important; }
.rems-view-shell .my-sr-filter-card .my-sr-filter-reset {
    color:#006b30 !important;
    background:#fff !important;
    border:1px solid #cde2d3 !important;
    box-shadow:none !important;
}
.rems-view-shell .my-sr-filter-card .my-sr-filter-reset:hover {background:#f2f8f3 !important;}
.rems-view-shell .my-sr-card { margin: 0; }
/* Same heading rhythm and typography as Extension Programs (.panel-header). */
.rems-view-shell .my-sr-records-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:15px;
    min-height:66px;
    padding:14px 18px;
    border-bottom:1px solid rgba(0,122,51,.09);
}
.rems-view-shell .my-sr-head-title {
    display:flex;
    align-items:center;
    gap:10px;
    min-width:0;
}
.rems-view-shell .my-sr-head-icon {
    display:inline-flex;
    justify-content:center;
    align-items:center;
    flex:0 0 36px;
    width:36px;
    min-width:36px;
    height:36px;
    border-radius:10px;
    color:#007a33;
    background:#edf7ef;
    font-size:16px;
}
.rems-view-shell .my-sr-head-title h2 {
    margin:0 !important;
    font-family:"Century Gothic", sans-serif !important;
    font-size:15px !important;
    font-weight:700 !important;
    line-height:1.3 !important;
    letter-spacing:0 !important;
    color:#101810 !important;
}
.rems-view-shell .my-sr-record-count {
    display:inline-flex; align-items:center; justify-content:center;
    min-height:30px;
    padding:6px 12px;
    border-radius:999px;
    color:#43664d;
    background:#f1f8f3;
    border:1px solid #d5e8da;
    font:700 10px/1 "Century Gothic",Arial,sans-serif;
    white-space:nowrap;
}
.rems-view-shell .my-sr-table-wrap { width:100%; overflow-x:auto; }
.rems-view-shell .my-sr-table {
    table-layout:fixed;
    width:100%;
    min-width:0;
    margin:0;
}
.rems-view-shell .my-sr-col-request {width:35%;}
.rems-view-shell .my-sr-col-service {width:17%;}
.rems-view-shell .my-sr-col-date {width:17%;}
.rems-view-shell .my-sr-col-status {width:15%;}
.rems-view-shell .my-sr-col-action {width:16%;}
.rems-view-shell .my-sr-table th {
    text-align:left !important;
    padding:14px 16px !important;
    background:#f7faf7 !important;
    font-size:9px !important;
}
.rems-view-shell .my-sr-table th:last-child,
.rems-view-shell .my-sr-table td:last-child {text-align:center !important;}
.rems-view-shell .my-sr-table td {padding:18px 16px !important;overflow-wrap:anywhere;}
.rems-view-shell .my-sr-table .my-sr-title {
    display:block;
    font:800 12px/1.45 "Century Gothic",Arial,sans-serif;
    color:#172a1e;
}
.rems-view-shell .my-sr-table .my-sr-code {font-size:10px;line-height:1.5;color:#78877d;}
.rems-view-shell .my-sr-table .my-sr-row-actions {
    display:flex;
    justify-content:center;
    align-items:center;
    flex-wrap:wrap;
    gap:7px;
}
.rems-view-shell .my-sr-table .my-sr-row-actions form {margin:0;}
.rems-view-shell .my-sr-table .my-sr-eye-btn {
    display:inline-flex;
    justify-content:center;
    align-items:center;
    width:38px !important;
    min-width:38px !important;
    height:36px !important;
    min-height:36px !important;
    padding:0 !important;
    border:1px solid #cde2d3 !important;
    border-radius:10px !important;
    color:#007a33 !important;
    background:#fff !important;
    box-shadow:none !important;
}
.rems-view-shell .my-sr-table .my-sr-eye-btn:hover {color:#005225 !important;background:#edf7f0 !important;border-color:#a8d2b4 !important;}
.rems-view-shell .my-sr-table .my-sr-row-actions > .btn:not(.my-sr-eye-btn),
.rems-view-shell .my-sr-table .my-sr-row-actions form .btn {
    min-height:34px;
    white-space:normal;
    text-align:center;
}
.rems-view-shell .my-sr-empty {
    min-height:210px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:5px;
    padding:36px 16px !important;
    font-size:11px;
}
.rems-view-shell .my-sr-empty i {font-size:22px !important;margin-bottom:6px;}
.rems-view-shell .my-sr-empty strong {font:700 13px/1.4 "Century Gothic",Arial,sans-serif;color:#405549;}
@media(max-width:1000px) {
    .rems-view-shell .my-sr-filter-card .my-sr-filters {grid-template-columns:minmax(190px,1fr) minmax(155px,.65fr);}
    .rems-view-shell .my-sr-filter-card .my-sr-filter-actions {grid-column:1/-1;justify-content:flex-end;}
    .rems-view-shell .eso-service-toolbar .eso-service-filters {grid-template-columns:minmax(220px,1fr) minmax(180px,.8fr);}
    .rems-view-shell .eso-service-toolbar .eso-service-filter-actions {grid-column:1/-1;justify-content:flex-end;}
}
@media(max-width:700px) {
    .rems-view-shell .my-sr-filter-card {padding:15px;}
    .rems-view-shell .my-sr-filter-card .my-sr-filters {grid-template-columns:1fr;gap:12px;}
    .rems-view-shell .my-sr-filter-card .my-sr-filter-actions {grid-column:auto;justify-content:stretch;}
    .rems-view-shell .my-sr-filter-card .my-sr-filter-actions .btn {flex:1;}
    .rems-view-shell .my-sr-records-head {padding:14px;}
    .rems-view-shell .my-sr-table {min-width:650px;}
    .rems-view-shell .eso-service-toolbar .eso-service-filters {grid-template-columns:1fr;gap:12px;}
    .rems-view-shell .eso-service-toolbar .eso-service-filter-actions {grid-column:auto;justify-content:stretch;}
    .rems-view-shell .eso-service-toolbar .eso-service-filter-actions .btn {flex:1;}
}
/* Service Request Information — match Extension Programs card heading exactly.
   Typography and icon sizing only; no form or workflow changes. */
.rems-view-shell .service-request-form-card .service-request-heading {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
}
.rems-view-shell .service-request-form-card .service-request-heading h2 {
    margin: 0 !important;
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    line-height: 1.3 !important;
    letter-spacing: 0 !important;
}
.rems-view-shell .service-request-form-card .service-request-heading-icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 36px !important;
    min-width: 36px !important;
    height: 36px !important;
    border-radius: 10px !important;
    background: #edf7ef !important;
    color: #007a33 !important;
    font-size: 16px !important;
}

/* ===============================================================
   TRAINING & TECHNICAL SERVICES — CONSISTENT FULL-WIDTH CONTROLS
   Applies to ESO's three list screens + Extensionist's requests.
   Only presentation changes; PHP actions/status flow stay intact.
   =============================================================== */
.rems-view-shell .eso-service-toolbar,
.rems-view-shell .my-sr-filter-card {
    container: service-filters / inline-size;
}

/* One aligned row on desktop: spacious search, flexible status, actions. */
.rems-view-shell .eso-service-toolbar .eso-service-filters,
.rems-view-shell .my-sr-filter-card .my-sr-filters {
    display: grid !important;
    grid-template-columns: minmax(0, 1.85fr) minmax(0, .95fr) max-content !important;
    align-items: end !important;
    gap: 12px !important;
    width: 100% !important;
    margin: 0 !important;
}
.rems-view-shell .eso-service-filter-search,
.rems-view-shell .eso-service-filter-status,
.rems-view-shell .my-sr-filter-search,
.rems-view-shell .my-sr-filter-status {
    width: 100%;
    min-width: 0 !important;
}
.rems-view-shell .eso-service-toolbar .form-label,
.rems-view-shell .my-sr-filter-card .form-label {
    display: block;
    margin-bottom: 7px !important;
    color: #526456 !important;
    font: 700 10px/1.4 "Century Gothic", Arial, sans-serif !important;
    letter-spacing: .025em;
}
.rems-view-shell .eso-service-filters .form-control,
.rems-view-shell .eso-service-filters .form-select,
.rems-view-shell .my-sr-filters .form-control,
.rems-view-shell .my-sr-filters .form-select {
    box-sizing: border-box;
    display: block;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    height: 44px !important;
    min-height: 44px !important;
    margin: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    text-overflow: ellipsis;
}
.rems-view-shell .eso-service-search-field,
.rems-view-shell .my-sr-search-field {
    position: relative;
    width: 100%;
    min-width: 0;
}
.rems-view-shell .eso-service-search-field > i,
.rems-view-shell .my-sr-search-field > i {
    position: absolute;
    top: 50%;
    left: 14px;
    z-index: 2;
    transform: translateY(-50%);
    color: #658071;
    font-size: 14px;
    pointer-events: none;
}
.rems-view-shell .eso-service-search-field > .form-control,
.rems-view-shell .my-sr-search-field > .form-control {
    padding-left: 40px !important;
}
.rems-view-shell .eso-service-filter-actions,
.rems-view-shell .my-sr-filter-card .my-sr-filter-actions {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 9px !important;
    min-width: 0;
    margin: 0 !important;
}
.rems-view-shell .eso-service-filter-actions .btn,
.rems-view-shell .my-sr-filter-card .my-sr-filter-actions .btn {
    display: inline-flex !important;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    min-width: 85px !important;
    width: auto;
    height: 44px !important;
    min-height: 44px !important;
    margin: 0 !important;
    padding: 0 13px !important;
    white-space: nowrap;
}
/* Inputs in create/edit request and ESO schedule always fill their columns. */
.rems-view-shell .service-request-form-card .row > [class*="col-"],
.rems-view-shell .eso-service-details .row > [class*="col-"] { min-width: 0; }
.rems-view-shell .service-request-form-card .form-control,
.rems-view-shell .service-request-form-card .form-select,
.rems-view-shell .eso-service-details .form-control,
.rems-view-shell .eso-service-details .form-select {
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    min-width: 0;
}
.rems-view-shell .service-request-form-card .input-group { flex-wrap: nowrap; }
.rems-view-shell .service-request-form-card .input-group .service-request-eye {
    flex: 0 0 38px;
    width: 38px !important;
    min-width: 38px !important;
}
/* Keep ESO action buttons readable and aligned while details can wrap. */
.rems-view-shell .eso-service-details .btn,
.rems-view-shell .my-sr-details .btn {
    white-space: nowrap;
}

/* Responsive: switch to two columns only when the CARD is too narrow. */
@container service-filters (max-width: 780px) {
    .rems-view-shell .eso-service-toolbar .eso-service-filters,
    .rems-view-shell .my-sr-filter-card .my-sr-filters {
        grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr) !important;
    }
    .rems-view-shell .eso-service-filter-actions,
    .rems-view-shell .my-sr-filter-card .my-sr-filter-actions {
        grid-column: 1 / -1 !important;
        justify-content: flex-end !important;
    }
}
@container service-filters (max-width: 500px) {
    .rems-view-shell .eso-service-toolbar .eso-service-filters,
    .rems-view-shell .my-sr-filter-card .my-sr-filters {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 12px !important;
    }
    .rems-view-shell .eso-service-filter-actions,
    .rems-view-shell .my-sr-filter-card .my-sr-filter-actions {
        grid-column: 1 !important;
        justify-content: stretch !important;
    }
    .rems-view-shell .eso-service-filter-actions .btn,
    .rems-view-shell .my-sr-filter-card .my-sr-filter-actions .btn {
        flex: 1 1 0;
        min-width: 0 !important;
    }
}

/* Extensionist: secondary actions live in the request details, not in the records table. */
.rems-view-shell .my-sr-detail-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    flex-wrap: wrap;
    gap: 10px !important;
    margin-top: 22px !important;
    padding-top: 17px !important;
    border-top: 1px solid #e7eee8 !important;
}
.rems-view-shell .my-sr-detail-actions form { margin: 0 !important; }
.rems-view-shell .my-sr-detail-actions .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    height: 42px !important;
    min-height: 42px !important;
    padding: 0 16px !important;
    margin: 0 !important;
    border-radius: 10px !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    text-align: center !important;
}
.rems-view-shell .my-sr-detail-actions .my-sr-back-to-list {
    margin-left: auto !important;
    background: #fff !important;
    border-color: #d7e3d9 !important;
    color: #31523a !important;
}
@media (max-width: 650px) {
    .rems-view-shell .my-sr-detail-actions { flex-direction: column; align-items: stretch !important; }
    .rems-view-shell .my-sr-detail-actions form,
    .rems-view-shell .my-sr-detail-actions .btn { width: 100% !important; }
    .rems-view-shell .my-sr-detail-actions .my-sr-back-to-list { margin-left: 0 !important; }
}

/* ESO Service Requests: one full-width remarks field and one aligned action row. */
.rems-view-shell .eso-service-review-controls {
    width: 100%;
    min-width: 0;
}
.rems-view-shell .eso-service-remarks-form {
    display: block;
    width: 100%;
    min-width: 0;
    margin: 0 0 14px !important;
}
.rems-view-shell .eso-service-remarks-form .form-label {
    display: block;
    width: 100%;
    margin: 0 0 8px !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    color: #344739 !important;
}
.rems-view-shell .eso-service-remarks-form textarea.form-control {
    display: block;
    box-sizing: border-box;
    width: 100% !important;
    min-width: 0;
    max-width: 100% !important;
    min-height: 110px;
    margin: 0 !important;
    padding: 12px 14px !important;
    border: 1px solid #d7e4d9 !important;
    border-radius: 11px !important;
    resize: vertical;
}
.rems-view-shell .eso-service-review-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 10px !important;
    width: 100%;
}
.rems-view-shell .eso-service-review-actions form {
    display: flex;
    align-items: center;
    margin: 0 !important;
}
.rems-view-shell .eso-service-review-actions .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 42px !important;
    min-height: 42px !important;
    margin: 0 !important;
    padding: 0 16px !important;
    border-radius: 10px !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    text-align: center;
    white-space: nowrap;
}
.rems-view-shell .eso-service-review-actions .eso-service-back-btn {
    margin-left: auto !important;
    background: #fff !important;
    border-color: #d7e3d9 !important;
    color: #31523a !important;
    box-shadow: none !important;
}
.rems-view-shell .eso-service-review-actions .eso-service-back-btn:hover {
    background: #f4faf5 !important;
    color: #006b30 !important;
}
@media (max-width: 650px) {
    .rems-view-shell .eso-service-review-actions {
        align-items: stretch !important;
        flex-direction: column;
    }
    .rems-view-shell .eso-service-review-actions form,
    .rems-view-shell .eso-service-review-actions .btn {
        width: 100% !important;
    }
    .rems-view-shell .eso-service-review-actions .eso-service-back-btn {
        margin-left: 0 !important;
    }
}
/* Document links: explicit label instead of an eye-only button. */
.rems-view-shell a.btn.service-document-view-link:has(> .bi-eye) {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    width: auto !important;
    min-width: 94px !important;
    min-height: 36px !important;
    padding: 0 13px !important;
    border: 1px solid #cde2d3 !important;
    border-radius: 9px !important;
    background: #fff !important;
    color: #007a33 !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
    text-decoration: none !important;
    box-shadow: none !important;
}
.rems-view-shell a.btn.service-document-view-link:has(> .bi-eye):hover {
    background: #edf7f0 !important;
    border-color: #a8d2b4 !important;
    color: #005225 !important;
}
.rems-view-shell a.btn.service-document-view-link i.bi-eye {
    flex: 0 0 auto;
    font-size: 13px !important;
}

/* Detail-page actions: green primary and Back together on the right.
   Keep browser-native confirmation dialogs disabled only for direct completion. */
.rems-view-shell .my-sr-detail-actions,
.rems-view-shell .eso-service-review-actions,
.rems-view-shell .eso-service-detail-actions {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-end !important;
    flex-wrap: wrap !important;
    gap: 10px !important;
    width: 100%;
}
.rems-view-shell .my-sr-detail-actions .my-sr-back-to-list,
.rems-view-shell .eso-service-review-actions .eso-service-back-btn,
.rems-view-shell .eso-service-detail-actions .eso-service-back-btn {
    margin-left: 0 !important;
    margin-right: 0 !important;
}
.rems-view-shell .eso-service-detail-actions form {
    display: inline-flex;
    align-items: center;
    margin: 0 !important;
}
.rems-view-shell .eso-service-detail-actions .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    min-height: 42px !important;
    padding: 0 16px !important;
    margin: 0 !important;
    border-radius: 10px !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    text-align: center !important;
    white-space: normal;
}
.rems-view-shell .eso-service-detail-actions .eso-service-back-btn {
    border-color: #d7e3d9 !important;
    background: #fff !important;
    color: #31523a !important;
}
@media (max-width: 650px) {
    .rems-view-shell .my-sr-detail-actions,
    .rems-view-shell .eso-service-review-actions,
    .rems-view-shell .eso-service-detail-actions {
        flex-direction: row !important;
        justify-content: flex-end !important;
        align-items: center !important;
    }
    .rems-view-shell .my-sr-detail-actions form,
    .rems-view-shell .eso-service-review-actions form,
    .rems-view-shell .eso-service-detail-actions form {
        width: auto !important;
    }
    .rems-view-shell .my-sr-detail-actions .btn,
    .rems-view-shell .eso-service-review-actions .btn,
    .rems-view-shell .eso-service-detail-actions .btn {
        width: auto !important;
        max-width: 100% !important;
    }
}

/* ===============================================================
   TRAINING & TECHNICAL SERVICES — ROLE-UNIFIED STATUS COLORS
   Match the color-coded Monitoring & Evaluation status badges.
   Applied to ESO detail/list badges and Extensionist detail/list
   badges only; no database status values or workflows are changed.
   =============================================================== */
.rems-view-shell :is(.eso-service-status, .my-sr-badge)[data-status] {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 29px !important;
    padding: 6px 11px !important;
    border: 1px solid transparent !important;
    border-radius: 999px !important;
    font: 700 9px/1.2 "Century Gothic", Arial, sans-serif !important;
    letter-spacing: .02em !important;
    white-space: nowrap !important;
}
/* Gray — saved drafts. */
.rems-view-shell :is(.eso-service-status, .my-sr-badge)[data-status="Draft"] {
    background: #eef1ef !important;
    border-color: #e3e8e4 !important;
    color: #4b5750 !important;
}
/* Light blue — accepted for processing / in progress. */
.rems-view-shell :is(.eso-service-status, .my-sr-badge):is(
    [data-status="Submitted"],
    [data-status="Acknowledged"],
    [data-status="Assigned"],
    [data-status="Ongoing"]
) {
    background: #e7f1ff !important;
    border-color: #cbdff7 !important;
    color: #174b83 !important;
}
/* Light red — requires more information from the Extensionist. */
.rems-view-shell :is(.eso-service-status, .my-sr-badge)[data-status="Needs Information"] {
    background: #fde9e9 !important;
    border-color: #f3cbcb !important;
    color: #9b2f36 !important;
}
/* Light yellow — scheduled or waiting for final ESO action. */
.rems-view-shell :is(.eso-service-status, .my-sr-badge):is(
    [data-status="Scheduled"],
    [data-status="Awaiting ESO Completion"]
) {
    background: #fff3c4 !important;
    border-color: #f0de9d !important;
    color: #664f00 !important;
}
/* Light green — officially completed, identical to monitoring. */
.rems-view-shell :is(.eso-service-status, .my-sr-badge)[data-status="Completed"] {
    background: #e4f3e8 !important;
    border-color: #cae6d0 !important;
    color: #166335 !important;
}


/* === ASU-REMS SERVICES DARK THEME FINAL OVERRIDES === */
/* Keep this AFTER the page's visual design CSS. Earlier light-theme !important
   rules otherwise paint the service form, table cells and buttons white. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope {
    --rems-surface: #162e22;
    --rems-surface-alt: #1c392b;
    --rems-surface-head: #203f2f;
    --rems-border-dark: #395b46;
    --rems-text-dark: #eaf3eb;
    --rems-muted-dark: #bdd2c3;
}

/* Consistent dark Research-style shell, with no translucent/white card layers. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope > .page-content {
    background: #0b1d15 !important;
    background-image: none !important;
    color: var(--rems-text-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell.container-fluid {
    background: transparent !important;
    color: var(--rems-text-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar.asu-topbar {
    background: #11271b !important;
    border-bottom: 1px solid var(--rems-border-dark) !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar :is(.rems-topbar-brandline strong,.topbar-user-text strong) {
    color: #f2f8f0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar :is(.rems-topbar-brandline span,.topbar-user-text span) {
    color: #bdd2c3 !important;
}

/* The existing green/gold banner must stay bright in either theme. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .rems-view-heading {
    background: linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%) !important;
    color: #fff !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .rems-view-heading :is(h1,p,.rems-view-eyebrow) {
    color: #fff !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .rems-view-heading .rems-top-yellow-btn {
    background: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #003f1a !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .rems-view-heading .rems-top-yellow-btn:hover {
    background: #f0bd00 !important;
    border-color: #f0bd00 !important;
    color: #003f1a !important;
}

/* Main form and list panels: flat deep greens, matching Research Records. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .card,.service-request-form-card,.eso-service-card,.eso-service-details,
    .my-sr-card,.my-sr-details,.my-sr-filter-card,.my-sr-stat
) {
    background: var(--rems-surface) !important;
    background-image: none !important;
    border-color: var(--rems-border-dark) !important;
    color: var(--rems-text-dark) !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .service-request-form-card .card-body,.my-sr-card .card-body,
    .eso-service-card .card-body,.my-sr-details .card-body,.eso-service-details .card-body
) {
    background: transparent !important;
    color: var(--rems-text-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .service-request-heading,.my-sr-head-title
) {
    background: transparent !important;
    background-image: none !important;
    box-shadow: none !important;
    border-color: transparent !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .service-request-form-card .service-request-heading h2,
    .service-request-form-card h2,.my-sr-head-title h2,
    .my-sr-card h2,.my-sr-details h2,.eso-service-card h2,
    .eso-service-details h2,.my-sr-title,.my-sr-table .my-sr-title,
    .eso-service-table strong,.eso-service-details strong
) {
    background: transparent !important;
    color: #f0f7f0 !important;
}
/* Dark icon tiles like Research Records: no white/pale background. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .service-request-heading-icon,.my-sr-head-icon
) {
    background: #224c35 !important;
    background-image: none !important;
    color: #baf0c9 !important;
    border: 1px solid #36694b !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .service-request-heading-icon,.my-sr-head-icon
) i {
    color: inherit !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .my-sr-records-head,.eso-service-toolbar,.my-sr-toolbar
) {
    background: var(--rems-surface-head) !important;
    border-bottom-color: var(--rems-border-dark) !important;
    color: var(--rems-text-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .service-request-actions,.my-sr-detail-actions,
    .eso-service-review-controls,.eso-service-review-actions,
    .eso-service-detail-actions,.my-sr-foot
) {
    border-color: var(--rems-border-dark) !important;
    color: var(--rems-muted-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .service-request-form-card .form-label,.my-sr-filter-card .form-label,
    .eso-service-toolbar .form-label,.eso-service-remarks-form .form-label,
    .my-sr-detail label,.eso-service-grid label
) {
    color: #d5e7d9 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .my-sr-detail div,.eso-service-grid div,.my-sr-description,
    .eso-service-details .small,.my-sr-detail .small
) {
    color: #e6f2e8 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .my-sr-code,.my-sr-table .my-sr-code,.service-request-help,
    .service-request-form-card .service-request-help,
    .my-sr-foot,.text-muted,.small.text-muted
) {
    color: var(--rems-muted-dark) !important;
}

/* Form controls (including those introduced in the late shared view styles). */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .form-control,.form-select,.input-group-text,
    .service-request-form-card .form-control,
    .service-request-form-card .form-select,
    .my-sr-filter-card .form-control,
    .my-sr-filter-card .form-select,
    .eso-service-toolbar .form-control,
    .eso-service-toolbar .form-select,
    .eso-service-remarks-form textarea.form-control
) {
    background: #1c392b !important;
    background-color: #1c392b !important;
    color: #f2f8f1 !important;
    border-color: #4c7158 !important;
    box-shadow: none !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(input.form-control,textarea.form-control)::placeholder {
    color: #b7d0be !important;
    opacity: 1 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell select.form-select {
    appearance: none !important;
    -webkit-appearance: none !important;
    background-color: #1c392b !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23DDF2E1' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 14px 14px !important;
    padding-right: 38px !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell select.form-select option {
    color: #f2f8f1 !important;
    background: #1c392b !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.form-control,.form-select):focus {
    border-color: #82c697 !important;
    box-shadow: 0 0 0 2px rgba(100,184,128,.16) !important;
}

/* Research-style records tables: no white cells, alternating rows. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table-wrap,.eso-service-table-wrap) {
    background: var(--rems-surface) !important;
    border-color: var(--rems-border-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table,.eso-service-table) {
    background: var(--rems-surface) !important;
    color: var(--rems-text-dark) !important;
    border-color: var(--rems-border-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table,.eso-service-table) thead th {
    background: #203f2f !important;
    border-color: #3b5945 !important;
    color: #d8eadc !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table,.eso-service-table) tbody tr:nth-child(odd) td {
    background: #162e22 !important;
    border-color: #3b5945 !important;
    color: var(--rems-text-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table,.eso-service-table) tbody tr:nth-child(even) td {
    background: #1d3b2c !important;
    border-color: #3b5945 !important;
    color: var(--rems-text-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table,.eso-service-table) tbody tr:hover td {
    background: #254734 !important;
    color: #f4faf4 !important;
}

/* Buttons that earlier light styling forced to white. Primary actions remain green. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .rems-top-white-btn,.service-request-form-card a.rems-top-white-btn,
    .btn-outline-asu,.btn-outline-secondary,.btn-light.border,
    .service-request-actions .btn-outline-asu,
    .my-sr-filter-reset,.eso-service-reset-btn,
    .my-sr-detail-actions .my-sr-back-to-list,
    .eso-service-review-actions .eso-service-back-btn,
    .eso-service-detail-actions .eso-service-back-btn,
    a.btn.service-document-view-link,
    .my-sr-table .my-sr-eye-btn,.eso-service-eye-btn,.service-letter-view-eye
) {
    background: #214330 !important;
    background-image: none !important;
    border-color: #527861 !important;
    color: #dcefe0 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .rems-top-white-btn,.service-request-form-card a.rems-top-white-btn,
    .btn-outline-asu,.btn-outline-secondary,.btn-light.border,
    .service-request-actions .btn-outline-asu,
    .my-sr-filter-reset,.eso-service-reset-btn,
    .my-sr-detail-actions .my-sr-back-to-list,
    .eso-service-review-actions .eso-service-back-btn,
    .eso-service-detail-actions .eso-service-back-btn,
    a.btn.service-document-view-link,
    .my-sr-table .my-sr-eye-btn,.eso-service-eye-btn
):hover {
    background: #2b553c !important;
    border-color: #78ad89 !important;
    color: #fff !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.btn-asu,.service-request-submit,.my-sr-filter-submit,.eso-service-filter-btn) {
    background: #08752c !important;
    border-color: #168642 !important;
    color: #fff !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.btn-asu,.service-request-submit,.my-sr-filter-submit,.eso-service-filter-btn):hover {
    background: #0a652a !important;
    border-color: #238047 !important;
    color: #fff !important;
}
/* Back to Extension is intentionally gold, never overwritten as secondary. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .rems-view-heading .rems-header-back.rems-top-yellow-btn {
    background: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #003f1a !important;
}

/* Ancillary panels: keep their contrast and avoid pale patches. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.service-request-note,.my-sr-stat) {
    background: #1e3c2c !important;
    border-color: #41624c !important;
    color: #d0e6d5 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-record-count,.my-sr-stat span) {
    background: #254634 !important;
    border-color: #477057 !important;
    color: #d8eadc !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .my-sr-stat strong {
    color: #f0f7f0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.eso-service-empty,.my-sr-empty) {
    background: var(--rems-surface) !important;
    color: var(--rems-muted-dark) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.eso-service-empty,.my-sr-empty) :is(strong,div) {
    color: #d5e6d8 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .alert-success {
    background: #214734 !important;
    color: #d9f0df !important;
    border-color: #4b8c61 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .alert-warning {
    background: #4b3b23 !important;
    color: #f7e4b5 !important;
    border-color: #8b6e3e !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .alert-danger {
    background: #492b2b !important;
    color: #f6d5d5 !important;
    border-color: #965b5b !important;
}
/* Preserve the distinct, meaningful status-badge colors from the existing page. */

/* Match Service Request Information icon tile to Extension Programs (dark mode). */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .service-request-form-card .service-request-heading .service-request-heading-icon {
    background: #28513b !important;
    background-image: none !important;
    border: 1px solid #386a4d !important;
    border-radius: 12px !important;
    color: #086e37 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .service-request-form-card .service-request-heading .service-request-heading-icon i {
    color: #086e37 !important;
    background: transparent !important;
}


/* ============================================================
   MY SERVICE REQUESTS — DARK MODE VISUAL MATCH
   Match Extension Programs / Research Records without altering
   light mode, layout, services workflow or database operations.
   ============================================================ */

/* Identical dark icon tile to Extension Programs. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .my-sr-card .my-sr-head-icon,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .service-request-form-card .service-request-heading .service-request-heading-icon {
    background: #28513b !important;
    background-image: none !important;
    border: 1px solid #386a4d !important;
    color: #88daa6 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell :is(.my-sr-head-icon,.service-request-heading-icon) i {
    color: #88daa6 !important;
    background: transparent !important;
}

/* Completed should be a muted green pill, NOT the pale white one. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell :is(.my-sr-card,.my-sr-details)
.my-sr-badge[data-status="Completed"] {
    background: #194b32 !important;
    border: 1px solid #43865b !important;
    color: #d4f7df !important;
    box-shadow: none !important;
}

/* All other statuses use accessible, subdued dark-theme shades. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell :is(.my-sr-card,.my-sr-details)
.my-sr-badge[data-status="Draft"] {
    background: #304239 !important;
    border-color: #62796b !important;
    color: #e3ede6 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell :is(.my-sr-card,.my-sr-details)
.my-sr-badge:is([data-status="Submitted"],[data-status="Acknowledged"],[data-status="Assigned"],[data-status="Ongoing"]) {
    background: #263f51 !important;
    border-color: #4b7595 !important;
    color: #d5ebfc !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell :is(.my-sr-card,.my-sr-details)
.my-sr-badge[data-status="Needs Information"] {
    background: #522e34 !important;
    border-color: #98616a !important;
    color: #ffdae0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell :is(.my-sr-card,.my-sr-details)
.my-sr-badge[data-status="Scheduled"] {
    background: #554a25 !important;
    border-color: #927b3d !important;
    color: #ffe3a6 !important;
}

/* Ensure records headings and data have the same contrast as Research. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .my-sr-card .my-sr-records-head {
    background: #203e2e !important;
    border-bottom-color: #3d6049 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .my-sr-card .my-sr-head-title h2,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .my-sr-card .my-sr-table .my-sr-title {
    color: #f0f8f0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .my-sr-card .my-sr-table .my-sr-code {
    color: #bfd7c6 !important;
}

/* =============================================================
   VISIBLE SELECT ARROWS — TRAINING & TECHNICAL SERVICES
   CSS-drawn chevrons do not disappear when other dark-mode
   background rules override Bootstrap's select background image.
   Scoped to the three existing dropdowns; no PHP/JS changes.
   ============================================================= */
.rems-view-shell .my-sr-filter-status,
.rems-view-shell .eso-service-filter-status,
.rems-view-shell .service-request-form-card .col-md-6:has(> select#service_type) {
    position: relative !important;
}

.rems-view-shell .my-sr-filter-status::after,
.rems-view-shell .eso-service-filter-status::after,
.rems-view-shell .service-request-form-card .col-md-6:has(> select#service_type)::after {
    content: "";
    position: absolute;
    z-index: 3;
    right: 18px;
    bottom: 18px;
    width: 9px;
    height: 9px;
    border-right: 2px solid #41644d;
    border-bottom: 2px solid #41644d;
    border-radius: 1px;
    transform: rotate(45deg);
    pointer-events: none;
}

.rems-view-shell .my-sr-filter-status select#myServiceStatus.form-select,
.rems-view-shell .eso-service-filter-status select#esoServiceStatus.form-select,
.rems-view-shell .service-request-form-card select#service_type.form-select {
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    background-image: none !important;
    padding-right: 43px !important;
}

html[data-rems-theme="dark"] .rems-view-shell .my-sr-filter-status::after,
html[data-rems-theme="dark"] .rems-view-shell .eso-service-filter-status::after,
html[data-rems-theme="dark"] .rems-view-shell .service-request-form-card .col-md-6:has(> select#service_type)::after {
    border-right-color: #e7f5e9;
    border-bottom-color: #e7f5e9;
}

/* ESO Completed Services — match the Extensionist's dark green Completed pills.
   Scoped to office service cards/details; other statuses and light mode stay intact. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell :is(.eso-service-card,.eso-service-details)
.eso-service-status[data-status="Completed"] {
    background: #194b32 !important;
    border: 1px solid #43865b !important;
    color: #d4f7df !important;
    box-shadow: none !important;
}

/* ESO filters: prevent a dark inset rectangle behind Search, Status and actions.
   The toolbar is the panel; the inner form must be transparent. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .eso-service-toolbar > form.eso-service-filters,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope
.rems-view-shell .my-sr-filter-card > form.my-sr-filters {
    background: transparent !important;
    background-image: none !important;
    border: 0 !important;
    box-shadow: none !important;
    outline: none !important;
}

/* ESO request details: old pre-wrap on every div preserved source indentation
   as visible blank lines around the letter button. Keep wrapping only for
   user-entered Purpose text; use normal whitespace for structural containers. */
.rems-view-shell .eso-service-details .eso-service-grid > div,
.rems-view-shell .eso-service-details .eso-service-grid > div > div {
    white-space: normal !important;
}
.rems-view-shell .eso-service-details .eso-service-grid > .rems-service-purpose-item > div {
    white-space: pre-wrap !important;
}

/* ===========================================================
   REQUEST DETAILS — BOTH COORDINATOR AND ESO
   Four balanced rows, aligned labels/values, no empty full-width
   slots between Purpose / Request Letter and the other fields.
   =========================================================== */
.rems-view-shell .my-sr-details .my-sr-detail-grid,
.rems-view-shell .eso-service-details .eso-service-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    align-items: start !important;
    column-gap: 24px !important;
    row-gap: 16px !important;
    grid-auto-flow: row !important;
    margin: 0 !important;
    padding: 0 !important;
}
.rems-view-shell .my-sr-details .my-sr-detail-grid > .my-sr-detail,
.rems-view-shell .eso-service-details .eso-service-grid > div {
    min-width: 0 !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    align-self: start !important;
}
.rems-view-shell .my-sr-details .my-sr-detail-grid > .my-sr-detail label,
.rems-view-shell .eso-service-details .eso-service-grid > div > label {
    display: block !important;
    margin: 0 0 5px !important;
    line-height: 1.45 !important;
}
.rems-view-shell .my-sr-details .my-sr-detail-grid > .my-sr-detail > div,
.rems-view-shell .eso-service-details .eso-service-grid > div > div {
    margin: 0 !important;
    line-height: 1.55 !important;
    overflow-wrap: anywhere !important;
}
.rems-view-shell .my-sr-details .rems-service-submission-meta,
.rems-view-shell .eso-service-details .rems-service-submission-meta {
    display: block;
    margin-top: 5px !important;
    font-size: 10px !important;
    line-height: 1.4 !important;
}
.rems-view-shell .my-sr-details .service-document-view-link,
.rems-view-shell .eso-service-details .service-document-view-link {
    max-width: 100% !important;
}
@media (max-width: 680px) {
    .rems-view-shell .my-sr-details .my-sr-detail-grid,
    .rems-view-shell .eso-service-details .eso-service-grid {
        grid-template-columns: minmax(0, 1fr) !important;
        row-gap: 13px !important;
    }
    .rems-view-shell .my-sr-details .my-sr-detail-grid > *,
    .rems-view-shell .eso-service-details .eso-service-grid > * {
        grid-column: auto !important;
    }
}
</style>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
