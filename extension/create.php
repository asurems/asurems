<?php

require_once __DIR__ . '/../includes/auth.php';

requireLogin();
require_once __DIR__ . '/includes/extension_roles.php';


/*
|--------------------------------------------------------------------------
| EXTENSION COORDINATOR ONLY
|--------------------------------------------------------------------------
*/

if (!extensionCanSubmitProposal()) {

    redirect(
        BASE_URL . '/extension/index.php'
    );
}


$user = currentUser();

$pageTitle = 'Create Extension Program/Project';
$activePage = 'extension';

$errors = [];

/*
|--------------------------------------------------------------------------
| HTTPS SUPPORTING LINK — VALIDATION / EYE CONFIRMATION
|--------------------------------------------------------------------------
| A supporting link remains optional for a Draft. If supplied, it must be
| previewed by its owner through the eye button before the Draft is saved.
*/
function extensionCreateValidHttpsLink(string $url): bool
{
    if ($url === '' || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || preg_match('/\s/', $url)
    ) {
        return false;
    }

    return true;
}

/* Store eye-button confirmation in the authenticated PHP session. */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['supporting_link_action'] ?? '') === 'confirm_view'
) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (!verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid request token. Refresh and try again.']);
        exit;
    }

    $previewUrl = trim((string)($_POST['program_link'] ?? ''));
    if (!extensionCreateValidHttpsLink($previewUrl)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Enter a valid HTTPS link first.']);
        exit;
    }

    $_SESSION['extension_create_link_viewed'] = [
        'user_id' => (int)$user['user_id'],
        'link_hash' => hash('sha256', $previewUrl),
        'viewed_at' => time(),
    ];

    echo json_encode(['ok' => true]);
    exit;
}

/* Active official calls for proposal creation. */
$availableCalls = [];
$selectedCallId = 0;
try {
    $callStmt = $pdo->query("
        SELECT call_id, memo_title, memo_number, opens_on, closes_on
        FROM extension_calls
        WHERE status = 'Open'
          AND opens_on <= CURDATE()
          AND closes_on >= CURDATE()
        ORDER BY closes_on ASC, call_id DESC
    ");
    $availableCalls = $callStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Extension active calls load error: ' . $e->getMessage());
    $errors[] = 'Unable to load active Calls for Proposals. Check the Phase 2 database setup.';
}


/*
|--------------------------------------------------------------------------
| LOAD COORDINATOR INSTITUTIONAL ASSIGNMENT
|--------------------------------------------------------------------------
*/

$userStmt =
    $pdo->prepare("
        SELECT
            u.user_id,
            u.college_id,
            u.department_id,

            c.college_name,
            d.department_name

        FROM users u

        LEFT JOIN colleges c
            ON c.college_id = u.college_id

        LEFT JOIN departments d
            ON d.department_id = u.department_id

        WHERE u.user_id = ?

        LIMIT 1
    ");

$userStmt->execute([
    (int)$user['user_id']
]);

$institutionalData =
    $userStmt->fetch(
        PDO::FETCH_ASSOC
    );



/*
|--------------------------------------------------------------------------
| FIXED ASU CAMPUSES + COLLEGE / DEPARTMENT MASTER DATA
|--------------------------------------------------------------------------
| Campus is chosen independently from these five official form choices.
| College/Department is populated ONLY from active Admin Master Data
| (colleges and the departments assigned to each college).
| Do not repurpose college_id to store campus: they are different things.
*/
$campusOptions = [
    'ASU Kalibo Campus',
    'ASU Makato Campus',
    'ASU Ibijay Campus',
    'ASU New Washington Campus',
    'ASU Banga Campus'
];

$collegeOptions = [];
$departmentsByCollege = [];
$collegeDepartmentSelectionMap = [];
try {
    $collegeOptions = $pdo->query("
        SELECT college_id, college_name
        FROM colleges
        WHERE status = 'Active'
        ORDER BY college_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $departments = $pdo->query("
        SELECT d.department_id, d.college_id, d.department_name
        FROM departments d
        INNER JOIN colleges c ON c.college_id = d.college_id
        WHERE d.status = 'Active' AND c.status = 'Active'
        ORDER BY d.department_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($collegeOptions as $college) {
        $id = (int)$college['college_id'];
        $departmentsByCollege[$id] = [];
        $collegeDepartmentSelectionMap['college:' . $id] = [
            'college_id' => $id,
            'department_id' => null
        ];
    }
    foreach ($departments as $department) {
        $collegeId = (int)$department['college_id'];
        if (!isset($departmentsByCollege[$collegeId])) continue;
        $departmentsByCollege[$collegeId][] = $department;
        $collegeDepartmentSelectionMap['department:' . (int)$department['department_id']] = [
            'college_id' => $collegeId,
            'department_id' => (int)$department['department_id']
        ];
    }
} catch (Throwable $e) {
    error_log('Extension College/Department Master Data load error: ' . $e->getMessage());
    $errors[] = 'Unable to load College/Department choices from Master Data.';
}

/*
|--------------------------------------------------------------------------
| CAMPUS -> COLLEGE / DEPARTMENT ASSIGNMENTS
|--------------------------------------------------------------------------
| Campus names remain fixed in this form. The administrator maps active
| college/department records to those campuses without changing Master Data.
| Do not assume a college is offered at every campus.
*/
$unitCampuses = [];
try {
    $campusMappingRows = $pdo->query("
        SELECT campus, unit_type, unit_id
        FROM extension_campus_unit_map
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($campusMappingRows as $mapping) {
        $campus = (string)$mapping['campus'];
        $unitKey = (string)$mapping['unit_type'] . ':' . (int)$mapping['unit_id'];
        // Orphaned or inactive Master Data records must not be offered.
        if (!in_array($campus, $campusOptions, true)
            || !isset($collegeDepartmentSelectionMap[$unitKey])) {
            continue;
        }
        $unitCampuses[$unitKey][] = $campus;
    }
} catch (Throwable $e) {
    error_log('Extension campus unit mapping load error: ' . $e->getMessage());
    $errors[] = 'Campus assignments are not available. Import the campus mapping SQL and ask the administrator to configure assignments.';
}

// An institution/department from the user profile may belong to another
// campus. Never preselect it before the coordinator chooses a campus.
$selectedCampus = '';
$selectedCollegeDepartment = '';

/*
|--------------------------------------------------------------------------
| DEFAULT FORM VALUES
|--------------------------------------------------------------------------
*/

$programTitle = '';
$programCategory = '';
$programType = '';

/* Official ASU extension program classifications. */
$programTypes = [
    'SUSTAINABLE COMMUNITIES AND ENVIRONMENTAL RESILIENCE',
    'HUMAN WELLNESS, EDUCATION AND DEVELOPMENT',
    'INNOVATION, TECHNOLOGY, AND ECONOMIC ADVANCEMENT'
];

$targetCommunity = '';
$municipality = '';
$province = 'Aklan';

$objectives = '';
$programDescription = '';
$expectedOutput = '';

$fundingSource = '';
$estimatedBudget = '';
$targetParticipants = '';

$startDate = '';
$endDate = '';
$supportingLink = '';
$proponents = [];


/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $errors[] =
            'Invalid request. Please refresh the page and try again.';
    }


    $selectedCallId = filter_var($_POST['call_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

    $selectedCampus = is_string($_POST['campus'] ?? null)
        ? trim($_POST['campus']) : '';
    $selectedCollegeDepartment = is_string($_POST['college_department_id'] ?? null)
        ? trim($_POST['college_department_id']) : '';

    $programTitle =
        trim(
            $_POST['program_title'] ?? ''
        );

    $programCategory =
        trim(
            $_POST['program_category'] ?? ''
        );

    $programType =
        trim(
            $_POST['program_type'] ?? ''
        );

    $targetCommunity =
        trim(
            $_POST['target_community'] ?? ''
        );

    $municipality =
        trim(
            $_POST['municipality'] ?? ''
        );

    $province =
        trim(
            $_POST['province'] ?? ''
        );

    $objectives =
        trim(
            $_POST['objectives'] ?? ''
        );

    $programDescription =
        trim(
            $_POST['program_description'] ?? ''
        );

    $expectedOutput =
        trim(
            $_POST['expected_output'] ?? ''
        );

    $fundingSource =
        trim(
            $_POST['funding_source'] ?? ''
        );

    $estimatedBudget =
        trim(
            $_POST['estimated_budget'] ?? ''
        );

    $targetParticipants =
        trim(
            $_POST['target_participants'] ?? ''
        );

    $startDate =
        trim(
            $_POST['start_date'] ?? ''
        );

    $endDate =
        trim(
            $_POST['end_date'] ?? ''
        );

    $supportingLink = trim((string)($_POST['program_link'] ?? ''));


    /* Proponents are entered by the extension coordinator and saved with the draft. */
    $submittedProponents = $_POST['proponents'] ?? [];
    if (!is_array($submittedProponents)) {
        $errors[] = 'Invalid proponents list.';
    } elseif (count($submittedProponents) > 30) {
        $errors[] = 'Only up to 30 proponents can be added.';
    } else {
        $usedNames = [];
        foreach ($submittedProponents as $submittedName) {
            if (!is_string($submittedName)) {
                $errors[] = 'Invalid proponent name.';
                continue;
            }
            $cleanName = trim((string)(preg_replace('/\s+/u', ' ', $submittedName) ?? $submittedName));
            if ($cleanName === '') {
                continue;
            }
            if (mb_strlen($cleanName, 'UTF-8') > 150) {
                $errors[] = 'Each proponent name must not exceed 150 characters.';
                continue;
            }
            $nameKey = mb_strtolower($cleanName, 'UTF-8');
            if (isset($usedNames[$nameKey])) {
                $errors[] = 'Duplicate proponent names are not allowed.';
                continue;
            }
            $usedNames[$nameKey] = true;
            $proponents[] = $cleanName;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    /* Validate against the live database, not only the form options. */
    if ($selectedCallId < 1) {
        $errors[] = 'Select an active Call for Proposals.';
    } else {
        try {
            $verifyCall = $pdo->prepare("
                SELECT call_id FROM extension_calls
                WHERE call_id = ? AND status = 'Open'
                  AND opens_on <= CURDATE()
                  AND closes_on >= CURDATE()
                LIMIT 1
            ");
            $verifyCall->execute([$selectedCallId]);
            if (!$verifyCall->fetchColumn()) {
                $errors[] = 'The selected Call for Proposals is no longer accepting proposals.';
            }
        } catch (Throwable $e) {
            error_log('Extension call validation error: ' . $e->getMessage());
            $errors[] = 'Unable to verify the selected Call for Proposals.';
        }
    }

    // Campus is the separate fixed ASU list (not a Master Data college).
    if (!in_array($selectedCampus, $campusOptions, true)) {
        $errors[] = 'Select one of the five ASU campuses.';
    }

    // Server-side enforcement: a Master Data unit MUST belong to the chosen
    // campus, even if someone bypasses the browser's filtered dropdown.
    if (!isset($collegeDepartmentSelectionMap[$selectedCollegeDepartment])) {
        $errors[] = 'Select a valid College/Department from Master Data.';
    } elseif (!in_array($selectedCampus, $unitCampuses[$selectedCollegeDepartment] ?? [], true)) {
        $errors[] = 'The selected College/Department is not assigned to that campus.';
    }

    if ($programTitle === '') {
        $errors[] =
            'Program/Project title is required.';
    }

    if (
        mb_strlen(
            $programTitle
        ) > 255
    ) {
        $errors[] =
            'Program/Project title must not exceed 255 characters.';
    }

    if ($programCategory === '') {
        $errors[] =
            'Program/Project category is required.';
    }

    if ($programType === '') {
        $errors[] =
            'Program/Project type is required.';
    } elseif (!in_array($programType, $programTypes, true)) {
        $errors[] =
            'Select one of the three official program/project types.';
    }

    if ($targetCommunity === '') {
        $errors[] =
            'Target community or barangay is required.';
    }

    if ($objectives === '') {
        $errors[] =
            'Program/Project objectives are required.';
    }

    if ($programDescription === '') {
        $errors[] =
            'Program/Project description is required.';
    }

    if ($expectedOutput === '') {
        $errors[] =
            'Expected output is required.';
    }


    /*
    |--------------------------------------------------------------------------
    | BUDGET
    |--------------------------------------------------------------------------
    */

    $budgetValue = null;

    if ($estimatedBudget !== '') {

        if (
            !is_numeric(
                $estimatedBudget
            )
        ) {

            $errors[] =
                'Estimated budget must be a valid number.';

        } elseif (
            (float)$estimatedBudget < 0
        ) {

            $errors[] =
                'Estimated budget cannot be negative.';

        } else {

            $budgetValue =
                round(
                    (float)$estimatedBudget,
                    2
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TARGET PARTICIPANTS
    |--------------------------------------------------------------------------
    */

    $participantValue = null;

    if ($targetParticipants !== '') {

        if (
            filter_var(
                $targetParticipants,
                FILTER_VALIDATE_INT
            ) === false
            ||
            (int)$targetParticipants < 0
        ) {

            $errors[] =
                'Target participants must be a valid non-negative whole number.';

        } else {

            $participantValue =
                (int)$targetParticipants;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $startDate !== ''
        &&
        $endDate !== ''
    ) {

        $startTimestamp =
            strtotime(
                $startDate
            );

        $endTimestamp =
            strtotime(
                $endDate
            );

        if (
            $startTimestamp === false
            ||
            $endTimestamp === false
        ) {

            $errors[] =
                'Please enter valid program/project dates.';

        } elseif (
            $endTimestamp <
            $startTimestamp
        ) {

            $errors[] =
                'Program/Project end date cannot be earlier than the start date.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | OPTIONAL SUPPORTING LINK (NO FILE UPLOAD)
    |--------------------------------------------------------------------------
    */

    if ($supportingLink !== '') {
        if (!extensionCreateValidHttpsLink($supportingLink)) {
            $errors[] = 'Enter a valid HTTPS supporting document link.';
        } else {
            // The existing extension_documents.file_path field stores the URL.
            // Respect the actual column length rather than silently truncating it.
            try {
                $maxLinkStmt = $pdo->query("
                    SELECT CHARACTER_MAXIMUM_LENGTH
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'extension_documents'
                      AND COLUMN_NAME = 'file_path'
                    LIMIT 1
                ");
                $maxLinkLength = $maxLinkStmt->fetchColumn();
                if ($maxLinkLength !== false && $maxLinkLength !== null
                    && strlen($supportingLink) > (int)$maxLinkLength) {
                    $errors[] = 'The supporting link is too long for the database field. Use a shorter shareable HTTPS link.';
                }
            } catch (Throwable $e) {
                error_log('Extension supporting URL length check: ' . $e->getMessage());
                $errors[] = 'Unable to validate the supporting link storage.';
            }

            $linkViewConfirmation = $_SESSION['extension_create_link_viewed'] ?? null;
            if (!is_array($linkViewConfirmation)
                || (int)($linkViewConfirmation['user_id'] ?? 0) !== (int)$user['user_id']
                || !hash_equals(
                    hash('sha256', $supportingLink),
                    (string)($linkViewConfirmation['link_hash'] ?? '')
                )
                || time() - (int)($linkViewConfirmation['viewed_at'] ?? 0) > 3600
            ) {
                $errors[] = 'You must click the eye button to view the link before submitting.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE PROGRAM
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            $pdo->beginTransaction();


            $stmt =
                $pdo->prepare("
                    INSERT INTO extension_programs
                    (
                        coordinator_id,
                        call_id,
                        college_id,
                        department_id,
                        campus,

                        program_title,
                        program_category,
                        program_type,

                        target_community,
                        municipality,
                        province,

                        objectives,
                        program_description,
                        expected_output,

                        funding_source,
                        estimated_budget,
                        target_participants,

                        start_date,
                        end_date,

                        status,
                        progress_percentage
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        'Draft',
                        0
                    )
                ");


            $stmt->execute([

                (int)$user['user_id'],

                $selectedCallId,

                $collegeDepartmentSelectionMap[$selectedCollegeDepartment]['college_id'],

                $collegeDepartmentSelectionMap[$selectedCollegeDepartment]['department_id'],

                $selectedCampus,

                $programTitle,
                $programCategory,
                $programType,

                $targetCommunity,

                $municipality !== ''
                    ? $municipality
                    : null,

                $province !== ''
                    ? $province
                    : null,

                $objectives,
                $programDescription,
                $expectedOutput,

                $fundingSource !== ''
                    ? $fundingSource
                    : null,

                $budgetValue,
                $participantValue,

                $startDate !== ''
                    ? $startDate
                    : null,

                $endDate !== ''
                    ? $endDate
                    : null

            ]);


            $extensionId =
                (int)$pdo->lastInsertId();


            /* A separate table keeps every proponent attached to its extension program. */
            if ($proponents) {
                $proponentStmt = $pdo->prepare("
                    INSERT INTO extension_program_proponents
                        (extension_id, full_name, sort_order)
                    VALUES (?, ?, ?)
                ");
                foreach ($proponents as $index => $proponentName) {
                    $proponentStmt->execute([$extensionId, $proponentName, $index + 1]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE SUPPORTING HTTPS LINK
            |--------------------------------------------------------------------------
            | Use existing extension_documents columns to avoid a new table.
            | file_path contains the absolute external HTTPS link.
            */
            if ($supportingLink !== '') {
                $documentStmt = $pdo->prepare("
                    INSERT INTO extension_documents
                    (
                        extension_id,
                        uploaded_by,
                        document_type,
                        original_name,
                        stored_name,
                        file_path,
                        file_extension,
                        file_size
                    )
                    VALUES (?, ?, 'Program Proposal', ?, ?, ?, ?, ?)
                ");

                $documentStmt->execute([
                    $extensionId,
                    (int)$user['user_id'],
                    'Program Proposal Link',
                    'extension_' . $extensionId . '_external_link',
                    $supportingLink,
                    'url',
                    0
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | STATUS HISTORY
            |--------------------------------------------------------------------------
            */

            $historyStmt =
                $pdo->prepare("
                    INSERT INTO extension_status_history
                    (
                        extension_id,
                        changed_by,
                        old_status,
                        new_status,
                        remarks
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        NULL,
                        'Draft',
                        ?
                    )
                ");


            $historyRemarks = $supportingLink !== ''
                ? 'Extension program/project draft created with supporting HTTPS link.'
                : 'Extension program/project draft created.';


            $historyStmt->execute([

                $extensionId,

                (int)$user['user_id'],

                $historyRemarks

            ]);


            $pdo->commit();
            unset($_SESSION['extension_create_link_viewed']);


            setFlash(
                'success',
                'Extension program/project was saved successfully as a draft.'
            );


            // Show the newly created draft immediately. The coordinator can
            // review it and use its existing Submit for Review action here.
            header('Location: ' . BASE_URL . '/extension/view.php?id=' . $extensionId, true, 303);
            exit;


        } catch (
            Throwable $e
        ) {

            if (
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            error_log('Extension create save error: ' . $e->getMessage());
            $errors[] = (strpos($e->getMessage(), 'extension_program_proponents') !== false)
                ? 'The proponents database table is missing. Import the provided SQL update before saving.'
                : 'Unable to save the extension program/project. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

$initials = '';

if (
    !empty(
        $user['first_name']
    )
) {
    $initials .=
        strtoupper(
            substr(
                $user['first_name'],
                0,
                1
            )
        );
}

if (
    !empty(
        $user['last_name']
    )
) {
    $initials .=
        strtoupper(
            substr(
                $user['last_name'],
                0,
                1
            )
        );
}


require __DIR__ .
    '/../includes/header.php';

require __DIR__ .
    '/../includes/sidebar.php';

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

html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-form-section,.extension-form-body,.extension-funding-fields,.extension-upload-control,.upload-box) {background:#162f22!important;border-color:#3d5f49!important;color:#e9f4ec!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-form-section-title,.extension-form-header h2,.extension-form-body .fw-semibold,.upload-box strong) {color:#eef7ef!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-call-link,.extension-upload-note,.extension-form-body .text-muted) {color:#c0d7c6!important}

</style>
<style>
/* ASU-REMS Extension — match Research create.php's final visual language. */
:root {
    --asu-green: #007a33;
    --asu-green-dark: #005c26;
    --asu-green-deep: #003f1a;
    --asu-yellow: #ffd51f;
    --asu-ink: #101810;
    --asu-muted: #657068;
}

.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a {
    font-family: "Century Gothic", sans-serif !important;
}
.main-page {
    min-height: 100vh !important;
    background: linear-gradient(135deg, #dff1e3 0%, #e8f0e3 34%, #f1eee2 68%, #f4e6d5 100%) !important;
}
.page-content.extension-create-page {
    min-height: calc(100vh - 76px) !important;
    padding: 24px 28px 30px !important;
    background: radial-gradient(circle at 90% 5%, rgba(255,213,31,.10), transparent 25%),
                radial-gradient(circle at 8% 7%, rgba(0,122,51,.07), transparent 27%),
                linear-gradient(135deg, #dff1e3 0%, #e8f0e3 34%, #f1eee2 68%, #f4e6d5 100%) !important;
}
.extension-form-wrapper { max-width: 1120px !important; margin: 0 auto !important; }

/* Topbar — two-line Research / Extension brand, vertical green rule. */
.asu-topbar {
    min-height: 76px !important;
    height: 76px !important;
    padding: 0 30px !important;
    background: rgba(255,255,255,.97) !important;
    border-bottom: 1px solid rgba(0,122,51,.12) !important;
    box-shadow: 0 10px 26px rgba(16,24,16,.035) !important;
}
.asu-topbar .topbar-left { display: flex !important; align-items: center !important; gap: 14px !important; }
.asu-topbar .system-name { display: none !important; }
.extension-create-brand { position: relative; padding-left: 18px; line-height: 1.15; }
.extension-create-brand::before {
    content: ""; position: absolute; left: 0; top: 50%; width: 3px; height: 38px;
    transform: translateY(-50%); border-radius: 999px; background: var(--asu-green);
}
.extension-create-brand strong {
    display: block; color: var(--asu-ink); font-size: 17px; font-weight: 800;
    letter-spacing: -.015em;
}
.extension-create-brand span {
    display: block; margin-top: 4px; color: var(--asu-muted); font-size: 13px;
}
.asu-topbar .topbar-user-text strong { font-size: 15px !important; line-height: 1.1 !important; }
.asu-topbar .topbar-user-text span { margin-top: 4px !important; color: var(--asu-muted) !important; font-size: 12px !important; }
.asu-topbar .user-avatar {
    width: 46px !important; height: 46px !important; min-width: 46px !important;
    border-radius: 50% !important; background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important; font-size: 15px !important; font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;
}

/* Same green banner as Research create.php. */
.extension-form-heading {
    position: relative; display: flex !important; align-items: center !important;
    justify-content: space-between !important; gap: 18px !important;
    min-height: 96px !important; margin: 0 0 20px !important;
    padding: 20px 28px !important; overflow: hidden !important;
    border: 0 !important; border-radius: 18px !important;
    background: linear-gradient(112deg, #005c26 0%, #007a33 64%, #2c9a39 100%) !important;
    box-shadow: 0 18px 42px rgba(0,90,40,.16) !important;
}
.extension-form-heading::after {
    content: ""; position: absolute; right: -110px; top: -120px;
    width: 430px; height: 280px; transform: rotate(-9deg);
    border-radius: 42px; background: linear-gradient(135deg, rgba(255,213,31,.16), rgba(255,255,255,.035));
    pointer-events: none;
}
.extension-form-heading > * { position: relative; z-index: 2; }
.extension-form-heading h1 {
    margin: 0 !important; color: #fff !important; font-size: 30px !important;
    font-weight: 900 !important; line-height: 1.15 !important; letter-spacing: -.025em !important;
}
.extension-form-heading > .btn,
.extension-form-heading > .btn.btn-outline-asu {
    display: inline-flex !important; align-items: center !important; justify-content: center !important;
    gap: 4px; flex-shrink: 0 !important; height: 42px !important;
    min-height: 42px !important; padding: 0 14px !important;
    border: 0 !important; border-radius: 10px !important;
    background: #ffd51f !important; color: #003f1a !important;
    font-size: 10.5px !important; font-weight: 800 !important;
    box-shadow: 0 8px 18px rgba(255,213,31,.18) !important; white-space: nowrap;
}
.extension-form-heading > .btn i { color: #003f1a !important; }
.extension-form-heading > .btn:hover,
.extension-form-heading > .btn.btn-outline-asu:hover {
    background: #f0bd00 !important; color: #003f1a !important;
}
.extension-form-heading > .btn:hover i { color: #003f1a !important; }

/* Main form card: white, compact and aligned to the research form. */
.extension-form-card {
    overflow: hidden; border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important; background: rgba(255,255,255,.96) !important;
    box-shadow: 0 16px 38px rgba(16,24,16,.06) !important;
}
.extension-form-header {
    min-height: 68px; display: flex; align-items: center; gap: 12px;
    padding: 14px 18px !important; border-bottom: 1px solid rgba(0,122,51,.09) !important;
    background: rgba(255,255,255,.88) !important;
}
.extension-form-header-icon {
    width: 40px; height: 40px; min-width: 40px; display: inline-flex;
    align-items: center; justify-content: center; border-radius: 11px;
    background: #edf7ef; color: var(--asu-green); font-size: 18px;
}
.extension-form-header h2 {
    margin: 0 !important; color: var(--asu-ink) !important;
    font-family: "Century Gothic", sans-serif !important; font-size: 15px !important; font-weight: 700 !important;
}
.extension-form-body { padding: 20px !important; }
.extension-form-section { margin-bottom: 24px !important; }
.extension-form-section:last-of-type { margin-bottom: 0 !important; }
.extension-form-section-title {
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px !important;
    padding-bottom: 9px !important; border-bottom: 1px solid rgba(0,122,51,.08) !important;
    color: #283229 !important; font-family: "Century Gothic", sans-serif !important;
    font-size: 10px !important; font-weight: 700 !important;
    letter-spacing: .03em !important; text-transform: uppercase;
}
.extension-form-section-title i { color: var(--asu-green) !important; font-size: 13px !important; }
.extension-form-card .form-label {
    margin-bottom: 5px !important; color: #263229 !important; font-size: 10px !important; font-weight: 700 !important;
}
.extension-form-card .form-control,
.extension-form-card .form-select,
.extension-form-card .input-group-text {
    min-height: 42px !important; border: 1px solid #d7e0d8 !important;
    background: #fff !important; color: #263229 !important;
    font-size: 10.5px !important; box-shadow: none !important;
}
.extension-form-card .form-control,
.extension-form-card .form-select { border-radius: 10px !important; }
.extension-form-card .form-select {
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23007632' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important; background-position: right 16px center !important;
    background-size: 16px 16px !important; padding-right: 44px !important;
}
.extension-form-card textarea.form-control { min-height: 108px !important; line-height: 1.6 !important; resize: vertical; }
.extension-form-card .form-control:focus,
.extension-form-card .form-select:focus {
    border-color: #76ac82 !important; box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;
}
.extension-form-card .input-group-text { border-radius: 10px 0 0 10px !important; }
.extension-form-card .input-group .form-control { border-radius: 0 10px 10px 0 !important; min-width: 0; }
.extension-form-card input:disabled { background: #f5f7f5 !important; color: #6d766f !important; }
.extension-form-card #call_id + .text-muted { color: #7b6b37 !important; font-size: 9.5px !important; }
.extension-call-link {
    display: inline-block; margin-top: 6px; color: var(--asu-green) !important;
    font-size: 9.5px !important; font-weight: 700; text-decoration: none;
}
.extension-call-link:hover { text-decoration: underline; color: var(--asu-green-dark) !important; }
/* Funding, participants and schedule: balanced 3 + 2 arrangement, no empty grid cell. */
.extension-funding-fields {
    display: grid !important;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    grid-template-areas:
        "funding funding budget budget participants participants"
        "start start start end end end";
    column-gap: 20px !important;
    row-gap: 20px !important;
    margin: 0 !important;
    align-items: start;
}
.extension-funding-fields > div { width: auto !important; max-width: none !important; min-width: 0; padding: 0 !important; }
.extension-funding-fields > div:nth-child(1) { grid-area: funding; }
.extension-funding-fields > div:nth-child(2) { grid-area: budget; }
.extension-funding-fields > div:nth-child(3) { grid-area: participants; }
.extension-funding-fields > div:nth-child(4) { grid-area: start; }
.extension-funding-fields > div:nth-child(5) { grid-area: end; }

/* Compact supporting document row like Research's manuscript link row. */
.extension-form-card .upload-box {
    display: flex !important; align-items: center !important; gap: 16px !important;
    width: 100%; min-height: 88px; padding: 14px 18px !important;
    border: 1px solid rgba(0,122,51,.30) !important; border-radius: 15px !important;
    background: #f9fcfa !important; text-align: left !important;
}
.extension-upload-intro {
    display: flex; flex: 0 0 34%; align-items: center; gap: 13px; min-width: 0;
}
.extension-form-card .upload-icon {
    width: 42px !important; height: 42px !important; min-width: 42px !important;
    display: flex; align-items: center; justify-content: center; margin: 0 !important;
    border-radius: 12px !important; background: #edf7ef !important;
    color: #007a33 !important; font-size: 18px !important;
}
.extension-form-card .upload-box h5 {
    margin: 0 0 4px !important; color: #263229 !important;
    font-size: 12px !important; font-weight: 800 !important;
}
.extension-form-card .upload-box p {
    min-height: 0 !important; margin: 0 !important; color: #68766c !important;
    font-size: 9.5px !important; line-height: 1.4 !important;
}
.extension-upload-control { flex: 1 1 auto; min-width: 0; }
.extension-form-card .extension-link-row {
    display: flex; align-items: center; gap: 8px; width: 100%;
}
.extension-form-card .extension-link-row input[type="url"] {
    flex: 1 1 auto; min-width: 0; height: 42px !important;
}
.extension-form-card .extension-link-eye {
    width: 42px; min-width: 42px; height: 42px; min-height: 42px;
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0 !important; border: 1px solid #cde2d3;
    border-radius: 10px; background: #fff; color: #007a33;
    text-decoration: none; font-size: 16px; box-shadow: none;
}
.extension-form-card .extension-link-eye:not([aria-disabled="true"]):hover {
    background: #edf7f0; border-color: #a8d2b4; color: #005225;
}
.extension-form-card .extension-link-eye[aria-disabled="true"] {
    opacity: .48; cursor: not-allowed; pointer-events: none;
}
.extension-link-viewed {color: #007a33; font-weight: 700;}
.extension-upload-note { display: flex; align-items: flex-start; gap: 6px;
    margin-top: 6px; color: #818b84; font-size: 8.5px; line-height: 1.45;
}
.extension-upload-note i { color: var(--asu-green); }

/* Form actions and validation, matching Research. */
.form-actions {
    display: flex; justify-content: flex-end; align-items: center;
    gap: 9px !important; margin-top: 24px !important; padding-top: 17px !important;
    border-top: 1px solid rgba(0,122,51,.08) !important;
}
.form-actions .btn {
    display: inline-flex !important; align-items: center !important; justify-content: center !important;
    min-height: 42px !important; padding: 0 14px !important;
    border-radius: 10px !important; font-size: 10px !important; font-weight: 800 !important;
}
.form-actions .btn-outline-asu {
    border: 1px solid rgba(0,122,51,.20) !important;
    background: #fff !important; color: var(--asu-green) !important;
}
.form-actions .btn-outline-asu:hover { background: #f2f8f3 !important; color: var(--asu-green-dark) !important; }
.form-actions .btn-asu {
    border: 1px solid var(--asu-green) !important;
    background: linear-gradient(135deg,var(--asu-green),var(--asu-green-dark)) !important;
    color: #fff !important; box-shadow: 0 8px 18px rgba(0,122,51,.14) !important;
}
.form-actions .btn-asu:hover { background: var(--asu-green-dark) !important; color: #fff !important; }
.form-actions .btn:disabled { opacity: .55 !important; cursor: not-allowed !important; }
.form-errors {
    margin-bottom: 16px !important; padding: 13px 15px !important;
    border: 1px solid rgba(242,140,40,.26) !important;
    border-left: 4px solid #f28c28 !important; border-radius: 12px !important;
    background: #fff9f3 !important; color: #7c4516 !important;
    font-size: 10px !important;
}
.form-errors strong { display: block; margin-bottom: 6px; font-size: 10px; }
.content-footer { display: none !important; }
/* No page-load, hover-lift, or scrolling animations. */
.extension-create-page *, .extension-create-page *::before, .extension-create-page *::after {
    animation: none !important; transition: none !important; scroll-behavior: auto !important;
}

@media (max-width: 992px) {
    .extension-form-card .upload-box { align-items: stretch !important; flex-direction: column !important; gap: 12px !important; }
    .extension-upload-intro { flex-basis: auto; width: 100%; }
    .extension-upload-control { width: 100%; }
}
@media (max-width: 760px) {
    .asu-topbar { padding: 0 16px !important; }
    .extension-create-brand strong { font-size: 13px !important; }
    .extension-create-brand span { font-size: 10px !important; }
    .page-content.extension-create-page { padding: 18px 14px 24px !important; }
    .extension-form-heading {
        min-height: 0 !important; flex-direction: column !important;
        align-items: flex-start !important; padding: 18px !important;
    }
    .extension-form-heading h1 { font-size: 25px !important; }
    .extension-form-heading > .btn { align-self: flex-start !important; }
    .extension-form-body { padding: 16px !important; }
    .extension-funding-fields { grid-template-columns: minmax(0,1fr) !important;
        grid-template-areas: "funding" "budget" "participants" "start" "end"; row-gap: 17px !important; }
    .form-actions { align-items: stretch !important; flex-direction: column-reverse !important; }
    .form-actions .btn { width: 100% !important; }
}

/* CREATE EXTENSION — proposal details and proponent encoding, side by side. */
.extension-form-wrapper {max-width:1240px!important;}
.extension-call-section {
    margin-bottom:18px; padding:14px 18px; background:#fffdf5;
    border:1px solid #e4e8df; border-radius:12px;
}
.extension-call-section .mb-3 {margin-bottom:0!important;}
.extension-call-title {
    display:flex; align-items:center; gap:8px; margin-bottom:9px;
    color:#005c26; font-size:11px; font-weight:700;
}
.extension-call-section .form-select {width:100%;}
.extension-proposal-columns {
    display:grid; grid-template-columns:minmax(0,1.06fr) minmax(0,1fr);
    align-items:stretch; gap:16px; margin-bottom:24px;
}
.extension-dual-card {min-width:0; border:1px solid #dbe5da; border-radius:14px; background:#fff; overflow:hidden;}
.extension-dual-card-head {
    min-height:58px; display:flex; align-items:center; gap:11px;
    padding:10px 17px; background:#fff2c8; border-bottom:1px solid #e8dfbd;
    color:#004f25;
}
.extension-dual-card-head > i {font-size:18px; flex-shrink:0;}
.extension-dual-card-head h3 {font-size:15px!important; font-weight:800!important; color:#004f25!important; margin:0!important; line-height:1.3;}
.extension-proponents-subtitle {display:block; margin-top:2px; font-size:9.5px; font-weight:400; color:#6f745e;}
.extension-dual-card-body {padding:18px;}
.extension-dual-card-body .extension-form-section {margin-bottom:20px!important;}
.extension-dual-card-body .extension-form-section:last-child {margin-bottom:0!important;}
.extension-dual-card-body .extension-form-section-title {
    margin-bottom:13px!important; color:#004f25!important;
}
.extension-coordinator-field {margin-bottom:19px;}
.extension-coordinator-field .form-control:disabled {background:#f4f6f4!important; color:#66736a!important; opacity:1;}
.extension-proponent-add-row {display:flex; align-items:stretch; gap:9px;}
.extension-proponent-add-row .form-control {flex:1 1 auto; min-width:0;}
.extension-add-proponent-btn {
    display:inline-flex; justify-content:center; align-items:center; flex-shrink:0;
    gap:6px; min-height:42px; padding:0 12px; border:1px solid #e7b800;
    border-radius:10px; background:#ffd51f; color:#10230e; font-size:10px;
    font-weight:800; cursor:pointer; white-space:nowrap; box-shadow:none!important;
}
.extension-add-proponent-btn:hover {background:#efc500;}
.extension-proponents-help {display:block; min-height:12px; margin-top:5px; color:#ac4f31; font-size:9px;}
.extension-encoded-heading {
    margin-top:15px; padding:9px 11px; border-radius:7px 7px 0 0;
    background:#e8f4e4; color:#005c26; font-size:11px; font-weight:800;
}
.extension-proponents-table-wrap {border:1px solid #d8e3d7; border-radius:0 0 8px 8px; overflow-x:auto;}
.extension-proponents-table {width:100%; border-collapse:collapse; table-layout:fixed; font-size:10px;}
.extension-proponents-table thead {background:#eff8eb; color:#19442b;}
.extension-proponents-table th,.extension-proponents-table td {padding:10px 9px; border-bottom:1px solid #e4e9e4; text-align:left; vertical-align:middle; overflow-wrap:anywhere;}
.extension-proponents-table tr:last-child td {border-bottom:0;}
.extension-proponents-table th:first-child,.extension-proponents-table td:first-child {width:46px; text-align:center;}
.extension-proponents-table th:last-child,.extension-proponents-table td:last-child {width:65px; text-align:center;}
.extension-remove-proponent {border:0; padding:5px 8px; background:transparent; color:#dc2626; border-radius:6px; cursor:pointer;}
.extension-remove-proponent:hover {background:#fee2e2;}
.extension-proponents-empty {color:#758078; text-align:center!important; font-style:italic;}
.extension-proposal-columns + .extension-form-section {margin-top:0!important;}

/* Campus and College/Department keep the same row spacing and dropdown styling. */
.extension-master-data-note {display:block; margin-top:6px; font-size:9px; color:#718075; line-height:1.5;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-master-data-note {color:#bed1c3!important;}

/* Preserve readable green/yellow styling in existing ASU-REMS dark mode. */
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-call-section,
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-dual-card {
    background:#162e22!important; border-color:#395b46!important; color:#eaf3eb!important;
}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-call-title,
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-dual-card-body .extension-form-section-title {color:#d9f4de!important;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-dual-card-head {
    background:#203d2c!important; border-color:#48634d!important; color:#f0f8ee!important;
}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-dual-card-head h3 {color:#f0f8ee!important;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-proponents-subtitle {color:#b7d2b9!important;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-coordinator-field .form-control:disabled {
    background:#1c392b!important; color:#d4e6d4!important; border-color:#4c7158!important;
}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-encoded-heading,
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-proponents-table thead {
    background:#234330!important; color:#ebf5e9!important;
}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-proponents-table-wrap {border-color:#395b46!important;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-add-proponent-btn {background:#ffd51f!important; border-color:#e7b800!important; color:#10230e!important;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-remove-proponent {color:#ff8888!important;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-proponents-empty {color:#b7d0ba!important;}

@media (max-width: 1000px) {
    .extension-proposal-columns {grid-template-columns:minmax(0,1fr);}
}
@media (max-width: 600px) {
    .extension-dual-card-body {padding:14px;}
    .extension-proponent-add-row {flex-wrap:wrap;}
    .extension-proponent-add-row .form-control {flex-basis:100%;}
    .extension-add-proponent-btn {width:100%;}
}

</style>


<div class="main-page rems-extension-dark-scope">


<header class="asu-topbar">


<div class="topbar-left">

<button
    id="sidebarToggle"
    class="sidebar-toggle"
    type="button"
>
    <i class="bi bi-list"></i>
</button>

<div class="extension-create-brand">
    <strong>Research &amp; Extension Management System</strong>
    <span>Aklan State University</span>
</div>

</div>


<div class="topbar-user">

<div class="topbar-user-text">

<strong>
<?= e(fullName($user)); ?>
</strong>

<span>
<?= e(roleLabel($user['role'])); ?>
</span>

</div>

<div class="user-avatar">
<?= e($initials); ?>
</div>

</div>


</header>


<main class="page-content extension-create-page">


<div class="extension-form-wrapper">


<div class="extension-form-heading">

<div>

<h1>
Create Extension Program/Project
</h1>

</div>


<a
    href="<?= BASE_URL; ?>/extension/index.php"
    class="btn btn-outline-asu"
>
    <i class="bi bi-arrow-left me-1"></i>
    Back to Extension
</a>

</div>


<?php if ($errors): ?>

<div class="form-errors">

<strong>
Please review the following:
</strong>

<ul class="mb-0 mt-2">

<?php foreach (
    $errors
    as
    $error
): ?>

<li>
<?= e($error); ?>
</li>

<?php endforeach; ?>

</ul>

</div>

<?php endif; ?>


<section class="extension-form-card">


<div class="extension-form-header">

<div class="extension-form-header-icon">
<i class="bi bi-megaphone"></i>
</div>

<div>

<h2>
Extension Program/Project Information
</h2>

</div>

</div>


<div class="extension-form-body">


<form
    method="POST"
    autocomplete="off"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>



<div class="extension-call-section">
    <div class="extension-call-title"><i class="bi bi-megaphone"></i> Official Call for Proposals</div>
    <div class="mb-3">
    <label class="form-label" for="call_id">Call for Proposals *</label>
    <select id="call_id" name="call_id" class="form-select" required>
        <option value="">Select active call</option>
        <?php foreach ($availableCalls as $call): ?>
            <option value="<?= (int)$call['call_id']; ?>"
                <?= $selectedCallId === (int)$call['call_id'] ? 'selected' : ''; ?>>
                <?= e($call['memo_title']); ?>
                (until <?= e($call['closes_on']); ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!$availableCalls): ?>
        <p class="text-muted small mt-2">No active Call for Proposals is accepting submissions.</p>
    <?php endif; ?>
    <a class="extension-call-link" href="<?= BASE_URL; ?>/extension/calls.php">View official calls and memo links</a>
</div>
</div>

<div class="extension-proposal-columns">
    <section class="extension-dual-card" aria-labelledby="extensionProposalDetailsHeading">
        <div class="extension-dual-card-head">
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            <h3 id="extensionProposalDetailsHeading">Proposal Details</h3>
        </div>
        <div class="extension-dual-card-body">
<div class="extension-form-section">


<div class="extension-form-section-title">
<i class="bi bi-journal-text"></i>
Basic Program/Project Information
</div>


<div class="row g-3">


<div class="col-12">

<label class="form-label">
Program/Project Title *
</label>

<input
    type="text"
    name="program_title"
    class="form-control"
    maxlength="255"
    value="<?= e($programTitle); ?>"
    required
>

</div>


<div class="col-md-6">

<label class="form-label">
Program/Project Category *
</label>

<input
    type="text"
    name="program_category"
    class="form-control"
    maxlength="150"
    value="<?= e($programCategory); ?>"
    required
>

</div>


<div class="col-md-6">

<label class="form-label">
Program/Project Type *
</label>

<select
    name="program_type"
    class="form-select"
    required
>

<option value="">
Select Program/Project Type
</option>

<?php foreach (
    $programTypes
    as
    $type
): ?>

<option
    value="<?= e($type); ?>"
    <?= $programType === $type
        ? 'selected'
        : ''; ?>
>
<?= e($type); ?>
</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-6">
    <label class="form-label" for="extensionCampusId">Campus *</label>
    <select id="extensionCampusId" name="campus" class="form-select" required>
        <option value="">Select Campus</option>
        <?php foreach ($campusOptions as $campus): ?>
            <option value="<?= e($campus); ?>" <?= $selectedCampus === $campus ? 'selected' : ''; ?>>
                <?= e($campus); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-6">
    <label class="form-label" for="extensionCollegeDepartmentId">College/Department *</label>
    <select id="extensionCollegeDepartmentId" name="college_department_id" class="form-select" required>
        <option value="">Select College/Department</option>
        <?php foreach ($collegeOptions as $college): ?>
            <?php $collegeId = (int)$college['college_id']; ?>
            <optgroup label="<?= e($college['college_name']); ?>">
                <option value="college:<?= $collegeId; ?>"
                    data-campuses="<?= e(json_encode($unitCampuses['college:' . $collegeId] ?? [])); ?>"
                    <?= $selectedCollegeDepartment === 'college:' . $collegeId ? 'selected' : ''; ?>>
                    <?= e($college['college_name']); ?> (College)
                </option>
                <?php foreach ($departmentsByCollege[$collegeId] ?? [] as $department): ?>
                    <option value="department:<?= (int)$department['department_id']; ?>"
                        data-campuses="<?= e(json_encode($unitCampuses['department:' . (int)$department['department_id']] ?? [])); ?>"
                        <?= $selectedCollegeDepartment === 'department:' . (int)$department['department_id'] ? 'selected' : ''; ?>>
                        <?= e($department['department_name']); ?> (Department)
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
    <small class="extension-master-data-note" id="extensionCampusUnitsHelp" aria-live="polite" style="display:none"></small>
</div>
</div>


</div>



<div class="extension-form-section">


<div class="extension-form-section-title">
<i class="bi bi-geo-alt"></i>
Target Community
</div>


<div class="row g-3">


<div class="col-md-6">

<label class="form-label">
Target Community / Barangay *
</label>

<input
    type="text"
    name="target_community"
    class="form-control"
    maxlength="255"
    value="<?= e($targetCommunity); ?>"
    required
>

</div>


<div class="col-md-3">

<label class="form-label">
Municipality
</label>

<input
    type="text"
    name="municipality"
    class="form-control"
    maxlength="150"
    value="<?= e($municipality); ?>"
>

</div>


<div class="col-md-3">

<label class="form-label">
Province
</label>

<input
    type="text"
    name="province"
    class="form-control"
    maxlength="150"
    value="<?= e($province); ?>"
>

</div>


</div>


</div>




        </div>
    </section>

    <section class="extension-dual-card" aria-labelledby="extensionProponentsHeading">
        <div class="extension-dual-card-head">
            <i class="bi bi-people-fill" aria-hidden="true"></i>
            <div>
                <h3 id="extensionProponentsHeading">Proponents</h3>
                <small class="extension-proponents-subtitle">Encoded by coordinator</small>
            </div>
        </div>
        <div class="extension-dual-card-body">
            <div class="extension-coordinator-field">
                <label class="form-label" for="extensionCoordinatorName">Coordinator</label>
                <input type="text" id="extensionCoordinatorName" class="form-control"
                       value="<?= e(fullName($user)); ?>" disabled>
            </div>
            <div class="extension-proponent-input-group">
                <label class="form-label" for="extensionNewProponent">Proponent Full Name</label>
                <div class="extension-proponent-add-row">
                    <input type="text" id="extensionNewProponent" class="form-control"
                           maxlength="150" placeholder="Enter proponent's full name"
                           autocomplete="off">
                    <button type="button" id="extensionAddProponent" class="extension-add-proponent-btn">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        Add Proponent
                    </button>
                </div>
                <small class="extension-proponents-help" id="extensionProponentsFeedback" aria-live="polite"></small>
            </div>
            <div class="extension-encoded-heading">Encoded Proponents</div>
            <div class="extension-proponents-table-wrap">
                <table class="extension-proponents-table">
                    <thead>
                        <tr><th scope="col">No.</th><th scope="col">Full Name</th><th scope="col">Actions</th></tr>
                    </thead>
                    <tbody id="extensionProponentsBody">
                        <?php foreach ($proponents as $index => $proponentName): ?>
                        <tr data-extension-proponent-row>
                            <td class="extension-proponent-number"><?= $index + 1; ?></td>
                            <td>
                                <span><?= e($proponentName); ?></span>
                                <input type="hidden" name="proponents[]" value="<?= e($proponentName); ?>">
                            </td>
                            <td>
                                <button type="button" class="extension-remove-proponent" aria-label="Remove proponent" title="Remove proponent">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="extensionNoProponentsRow" <?= $proponents ? 'hidden' : ''; ?>>
                            <td colspan="3" class="extension-proponents-empty">No proponents added yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<div class="extension-form-section">


<div class="extension-form-section-title">
<i class="bi bi-file-text"></i>
Program/Project Content
</div>


<div class="row g-3">


<div class="col-12">

<label class="form-label">
Objectives *
</label>

<textarea
    name="objectives"
    class="form-control"
    required
><?= e($objectives); ?></textarea>

</div>


<div class="col-12">

<label class="form-label">
Program/Project Description *
</label>

<textarea
    name="program_description"
    class="form-control"
    required
><?= e($programDescription); ?></textarea>

</div>


<div class="col-12">

<label class="form-label">
Expected Output *
</label>

<textarea
    name="expected_output"
    class="form-control"
    required
><?= e($expectedOutput); ?></textarea>

</div>


</div>


</div>



<div class="extension-form-section">


<div class="extension-form-section-title">
<i class="bi bi-calendar3"></i>
Funding, Participants & Schedule
</div>


<div class="extension-funding-fields">


<div class="col-md-4">

<label class="form-label">
Funding Source
</label>

<input
    type="text"
    name="funding_source"
    class="form-control"
    maxlength="150"
    value="<?= e($fundingSource); ?>"
>

</div>


<div class="col-md-4">

<label class="form-label">
Estimated Budget
</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
    type="number"
    name="estimated_budget"
    class="form-control"
    min="0"
    step="0.01"
    value="<?= e($estimatedBudget); ?>"
>

</div>

</div>


<div class="col-md-4">

<label class="form-label">
Target Participants
</label>

<input
    type="number"
    name="target_participants"
    class="form-control"
    min="0"
    step="1"
    value="<?= e($targetParticipants); ?>"
>

</div>


<div class="col-md-6">

<label class="form-label">
Program/Project Start Date
</label>

<input
    type="date"
    name="start_date"
    class="form-control"
    value="<?= e($startDate); ?>"
>

</div>


<div class="col-md-6">

<label class="form-label">
Program/Project End Date
</label>

<input
    type="date"
    name="end_date"
    class="form-control"
    value="<?= e($endDate); ?>"
>

</div>


</div>


</div>



<div class="extension-form-section">

<div class="extension-form-section-title">
<i class="bi bi-link-45deg"></i>
Supporting Document Link
</div>

<div class="upload-box">
    <div class="extension-upload-intro">
        <div class="upload-icon"><i class="bi bi-link-45deg"></i></div>
        <div>
            <h5>Extension Program/Project Link</h5>
            <p>Optional while the program/project is still a Draft.</p>
        </div>
    </div>
    <div class="extension-upload-control">
        <div class="extension-link-row">
            <input
                type="url"
                id="extensionProgramLink"
                name="program_link"
                class="form-control"
                maxlength="2048"
                placeholder="https://..."
                value="<?= e($supportingLink); ?>"
                aria-label="Extension program/project supporting HTTPS link"
                autocomplete="off"
            >
            <a
                id="extensionProgramViewLink"
                class="extension-link-eye"
                href="#"
                target="_blank"
                rel="noopener noreferrer"
                title="View supporting link"
                aria-label="View supporting link"
                aria-disabled="true"
            ><i class="bi bi-eye" aria-hidden="true"></i></a>
        </div>
        <small class="extension-upload-note" id="extensionLinkNote">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>You must click the eye button to view the link before submitting.</span>
        </small>
    </div>
</div>

</div>


<div class="form-actions">


<a
    href="<?= BASE_URL; ?>/extension/index.php"
    class="btn btn-outline-asu"
>
Cancel
</a>


<button
    type="submit"
    id="extensionSaveDraftButton"
    class="btn btn-asu"
    <?= !$availableCalls ? 'disabled' : ''; ?>
>
<i class="bi bi-save me-1"></i>
Save as Draft
</button>


</div>


</form>


</div>


</section>


</div>


</main>


</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    /* Only College/Department records assigned to the chosen ASU campus. */
    const campusSelect = document.getElementById('extensionCampusId');
    const collegeDepartmentSelect = document.getElementById('extensionCollegeDepartmentId');
    const campusUnitsHelp = document.getElementById('extensionCampusUnitsHelp');

    function filterCampusUnits() {
        if (!campusSelect || !collegeDepartmentSelect) return;
        const selectedCampus = campusSelect.value;
        const currentUnit = collegeDepartmentSelect.value;
        let count = 0;
        let keepSelection = false;

        Array.from(collegeDepartmentSelect.querySelectorAll('optgroup')).forEach(function (group) {
            let shownInGroup = 0;
            Array.from(group.querySelectorAll('option')).forEach(function (option) {
                let allowedCampuses = [];
                try { allowedCampuses = JSON.parse(option.dataset.campuses || '[]'); }
                catch (err) { allowedCampuses = []; }
                const allowed = !!selectedCampus && allowedCampuses.includes(selectedCampus);
                option.hidden = !allowed;
                option.disabled = !allowed;
                if (allowed) {
                    count++;
                    shownInGroup++;
                    if (option.value === currentUnit) keepSelection = true;
                }
            });
            group.hidden = shownInGroup === 0;
            group.disabled = shownInGroup === 0;
        });
        if (!keepSelection) collegeDepartmentSelect.value = '';
        collegeDepartmentSelect.disabled = !selectedCampus || count === 0;
        if (campusUnitsHelp) {
            // Keep the dropdown clean; show guidance only if the chosen campus has no units.
            const noAssignedUnits = !!selectedCampus && count === 0;
            campusUnitsHelp.textContent = noAssignedUnits
                ? 'No College/Department assigned to this campus yet. Please contact the administrator.'
                : '';
            campusUnitsHelp.style.display = noAssignedUnits ? 'block' : 'none';
        }
    }

    if (campusSelect && collegeDepartmentSelect) {
        campusSelect.addEventListener('change', filterCampusUnits);
        filterCampusUnits();
    }

    /* Encode, immediately display and remove proponents without leaving the page. */
    const proponentInput = document.getElementById('extensionNewProponent');
    const addProponentButton = document.getElementById('extensionAddProponent');
    const proponentBody = document.getElementById('extensionProponentsBody');
    const emptyProponentsRow = document.getElementById('extensionNoProponentsRow');
    const proponentFeedback = document.getElementById('extensionProponentsFeedback');

    function proponentRows() {
        return proponentBody ? Array.from(proponentBody.querySelectorAll('tr[data-extension-proponent-row]')) : [];
    }

    function updateProponentNumbers() {
        const rows = proponentRows();
        rows.forEach(function (row, index) {
            row.querySelector('.extension-proponent-number').textContent = String(index + 1);
        });
        if (emptyProponentsRow) emptyProponentsRow.hidden = rows.length !== 0;
    }

    function addNewProponent() {
        if (!proponentInput || !proponentBody) return;
        const name = proponentInput.value.trim().replace(/\s+/g, ' ');
        if (!name) {
            proponentFeedback.textContent = 'Enter the full name of the proponent.';
            proponentInput.focus();
            return;
        }
        if (name.length > 150) {
            proponentFeedback.textContent = 'Name must not exceed 150 characters.';
            return;
        }
        if (proponentRows().length >= 30) {
            proponentFeedback.textContent = 'Only up to 30 proponents can be added.';
            return;
        }
        const duplicate = proponentRows().some(function (row) {
            return row.querySelector('input[name="proponents[]"]').value.toLocaleLowerCase() === name.toLocaleLowerCase();
        });
        if (duplicate) {
            proponentFeedback.textContent = 'This proponent is already on the list.';
            return;
        }

        const row = document.createElement('tr');
        row.setAttribute('data-extension-proponent-row', '');
        const numberCell = document.createElement('td');
        numberCell.className = 'extension-proponent-number';
        const nameCell = document.createElement('td');
        const label = document.createElement('span');
        label.textContent = name;
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'proponents[]';
        hidden.value = name;
        nameCell.append(label, hidden);
        const actionCell = document.createElement('td');
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'extension-remove-proponent';
        removeButton.title = 'Remove proponent';
        removeButton.setAttribute('aria-label', 'Remove proponent');
        const icon = document.createElement('i');
        icon.className = 'bi bi-trash';
        icon.setAttribute('aria-hidden', 'true');
        removeButton.appendChild(icon);
        actionCell.appendChild(removeButton);
        row.append(numberCell, nameCell, actionCell);
        proponentBody.insertBefore(row, emptyProponentsRow);
        proponentInput.value = '';
        proponentFeedback.textContent = '';
        updateProponentNumbers();
        proponentInput.focus();
    }

    if (proponentInput && addProponentButton && proponentBody) {
        addProponentButton.addEventListener('click', addNewProponent);
        proponentInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addNewProponent();
            }
        });
        proponentBody.addEventListener('click', function (event) {
            const remove = event.target.closest('.extension-remove-proponent');
            if (!remove) return;
            remove.closest('tr[data-extension-proponent-row]').remove();
            proponentFeedback.textContent = '';
            updateProponentNumbers();
        });
        updateProponentNumbers();
    }

    const urlInput = document.getElementById('extensionProgramLink');
    const eyeLink = document.getElementById('extensionProgramViewLink');
    const saveButton = document.getElementById('extensionSaveDraftButton');
    const note = document.getElementById('extensionLinkNote');
    const form = urlInput ? urlInput.closest('form') : null;
    if (!urlInput || !eyeLink || !saveButton || !form) return;

    const csrfInput = form.querySelector('input[name="csrf_token"]');
    const hasActiveCall = <?= $availableCalls ? 'true' : 'false'; ?>;
    let viewedUrl = '';

    function isValidHttps(value) {
        try {
            const parsed = new URL(value);
            return parsed.protocol === 'https:' && !!parsed.hostname &&
                !parsed.username && !parsed.password && !/\s/.test(value) &&
                value.length <= 2048;
        } catch (error) {
            return false;
        }
    }

    function updateControls() {
        const value = urlInput.value.trim();
        const valid = isValidHttps(value);
        const hasViewed = valid && viewedUrl === value;
        const enabled = hasActiveCall && (value === '' || hasViewed);
        eyeLink.href = valid ? value : '#';
        eyeLink.setAttribute('aria-disabled', valid ? 'false' : 'true');
        saveButton.disabled = !enabled;
        if (note) {
            const label = note.querySelector('span');
            if (label) {
                label.textContent = hasViewed
                    ? 'Link viewed. You can now save the draft.'
                    : 'You must click the eye button to view the link before submitting.';
                label.classList.toggle('extension-link-viewed', hasViewed);
            }
        }
    }

    urlInput.addEventListener('input', function () {
        viewedUrl = '';
        updateControls();
    });

    eyeLink.addEventListener('click', function (event) {
        const requestedUrl = urlInput.value.trim();
        if (!isValidHttps(requestedUrl)) {
            event.preventDefault();
            return;
        }

        // The anchor opens the HTTPS document in a new tab with noopener.
        // This POST records the eye-button action in the PHP user session.
        viewedUrl = '';
        updateControls();
        const body = new URLSearchParams();
        body.set('supporting_link_action', 'confirm_view');
        body.set('csrf_token', csrfInput ? csrfInput.value : '');
        body.set('program_link', requestedUrl);

        fetch(window.location.pathname, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        })
        .then(async function (response) {
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Unable to confirm the link view.');
            }
            if (urlInput.value.trim() === requestedUrl) {
                viewedUrl = requestedUrl;
                updateControls();
            }
        })
        .catch(function (error) {
            viewedUrl = '';
            updateControls();
            if (note) {
                const label = note.querySelector('span');
                if (label) label.textContent = error.message || 'Unable to verify the link view. Try again.';
            }
        });
    });

    form.addEventListener('submit', function (event) {
        const value = urlInput.value.trim();
        if (value !== '' && (!isValidHttps(value) || viewedUrl !== value)) {
            event.preventDefault();
            updateControls();
            urlInput.focus();
        }
    });

    updateControls();
});
</script>

<?php

require __DIR__ .
    '/../includes/footer.php';

?>
