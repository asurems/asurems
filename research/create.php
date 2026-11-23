<?php

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

/*
|--------------------------------------------------------------------------
| FACULTY ACCESS ONLY
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'faculty') {
    redirect(BASE_URL . '/research/index.php');
}

/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$user = currentUser();

$pageTitle  = 'Upload Manuscript';
$activePage = 'research';

$errors = [];

/*
|--------------------------------------------------------------------------
| FILE UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$maxFileSize = 10 * 1024 * 1024; // 10 MB

$proposalUploadDirectory =
    __DIR__ . '/../uploads/research/proposals';

$proposalAllowedExtensions = [
    'pdf',
    'doc',
    'docx'
];

$proposalAllowedMimeTypes = [
    'pdf' => [
        'application/pdf'
    ],

    'doc' => [
        'application/msword',
        'application/vnd.ms-office',
        'application/x-ole-storage',
        'application/CDFV2',
        'application/x-cfb',
        'application/octet-stream'
    ],

    'docx' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/octet-stream'
    ]
];


/*
|--------------------------------------------------------------------------
| SMALL HELPERS
|--------------------------------------------------------------------------
*/

function createResearchSafeText(?string $value): string
{
    return trim((string)$value);
}

function createResearchUploadedFileErrorLabel(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded file exceeds the allowed file size.',

        UPLOAD_ERR_PARTIAL =>
            'The file was only partially uploaded. Please try again.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server upload folder is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The uploaded file could not be written to the server.',

        UPLOAD_ERR_EXTENSION =>
            'The upload was stopped by a PHP extension.',

        default =>
            'Unable to upload the file. Please try again.'
    };
}

function createResearchValidateUpload(
    string $inputName,
    string $label,
    array $allowedExtensions,
    array $allowedMimeTypes,
    int $maxFileSize,
    array &$errors,
    bool $required = true
): ?array {

    if (
        !isset($_FILES[$inputName])
        ||
        (
            $_FILES[$inputName]['error']
            ?? UPLOAD_ERR_NO_FILE
        ) === UPLOAD_ERR_NO_FILE
    ) {
        if ($required) {
            $errors[] = $label . ' is required.';
        }

        return null;
    }

    $file = $_FILES[$inputName];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] =
            $label . ': ' .
            createResearchUploadedFileErrorLabel(
                (int)$file['error']
            );

        return null;
    }

    $fileSize = (int)($file['size'] ?? 0);

    if ($fileSize <= 0) {
        $errors[] = $label . ' is empty.';
        return null;
    }

    if ($fileSize > $maxFileSize) {
        $errors[] = $label . ' must not exceed 10 MB.';
        return null;
    }

    $originalName =
        basename(
            (string)($file['name'] ?? '')
        );

    $extension =
        strtolower(
            pathinfo(
                $originalName,
                PATHINFO_EXTENSION
            )
        );

    if (
        $extension === ''
        ||
        !in_array(
            $extension,
            $allowedExtensions,
            true
        )
    ) {
        $errors[] =
            $label .
            ' has an invalid format. Allowed formats: ' .
            strtoupper(
                implode(
                    ', ',
                    $allowedExtensions
                )
            ) .
            '.';

        return null;
    }

    if (
        empty($file['tmp_name'])
        ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        $errors[] = $label . ' is invalid.';
        return null;
    }

    $mimeType = null;

    if (function_exists('finfo_open')) {
        $fileInfo =
            finfo_open(
                FILEINFO_MIME_TYPE
            );

        if ($fileInfo !== false) {
            $mimeType =
                finfo_file(
                    $fileInfo,
                    $file['tmp_name']
                );

            finfo_close($fileInfo);
        }
    }

    if (
        $mimeType
        &&
        isset($allowedMimeTypes[$extension])
        &&
        !in_array(
            $mimeType,
            $allowedMimeTypes[$extension],
            true
        )
    ) {
        $errors[] =
            $label .
            ' does not match the selected file format.';

        return null;
    }

    return [
        'tmp_name'      => $file['tmp_name'],
        'original_name' => $originalName,
        'extension'     => $extension,
        'size'          => $fileSize
    ];
}

function createResearchEnsureDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (
        !mkdir(
            $directory,
            0755,
            true
        )
        &&
        !is_dir($directory)
    ) {
        throw new RuntimeException(
            'Unable to create upload directory.'
        );
    }
}

function createResearchMoveUploadedDocument(
    array $fileData,
    string $directory,
    int $researchId,
    string $prefix,
    string $databaseFolder
): array {

    createResearchEnsureDirectory($directory);

    $storedName =
        $prefix .
        '_' .
        $researchId .
        '_' .
        date('YmdHis') .
        '_' .
        bin2hex(random_bytes(8)) .
        '.' .
        $fileData['extension'];

    $physicalPath =
        $directory .
        DIRECTORY_SEPARATOR .
        $storedName;

    if (
        !move_uploaded_file(
            $fileData['tmp_name'],
            $physicalPath
        )
    ) {
        throw new RuntimeException(
            'Unable to save uploaded file.'
        );
    }

    return [
        'stored_name'   => $storedName,
        'physical_path' => $physicalPath,
        'database_path' => trim($databaseFolder, '/') . '/' . $storedName
    ];
}

function createResearchColumnExists(
    PDO $pdo,
    string $tableName,
    string $columnName
): bool {

    $stmt =
        $pdo->prepare("\n            SELECT COUNT(*)\n            FROM information_schema.COLUMNS\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = ?\n              AND COLUMN_NAME = ?\n        ");

    $stmt->execute([
        $tableName,
        $columnName
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function createResearchTableExists(
    PDO $pdo,
    string $tableName
): bool {

    $stmt =
        $pdo->prepare("\n            SELECT COUNT(*)\n            FROM information_schema.TABLES\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = ?\n        ");

    $stmt->execute([
        $tableName
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function createResearchTryAlter(
    PDO $pdo,
    string $sql
): void {

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        error_log(
            'Create Research Schema Patch Warning: ' .
            $e->getMessage()
        );
    }
}

function createResearchEnsurePhaseOneSchema(PDO $pdo): void
{
    /*
    |----------------------------------------------------------------------
    | SAFE AUTO-PATCH
    |----------------------------------------------------------------------
    | This prevents upload failure when the local database is still using
    | the older research_proposals / research_documents structure.
    */

    if (createResearchTableExists($pdo, 'research_proposals')) {

        if (!createResearchColumnExists($pdo, 'research_proposals', 'manuscript_no')) {
            createResearchTryAlter(
                $pdo,
                "ALTER TABLE research_proposals\n                 ADD COLUMN manuscript_no VARCHAR(80) NULL AFTER department_id"
            );
        }

        if (!createResearchColumnExists($pdo, 'research_proposals', 'submission_track')) {
            createResearchTryAlter(
                $pdo,
                "ALTER TABLE research_proposals\n                 ADD COLUMN submission_track VARCHAR(50) NULL DEFAULT 'REGULAR' AFTER manuscript_no"
            );
        }

        if (!createResearchColumnExists($pdo, 'research_proposals', 'workflow_status')) {
            createResearchTryAlter(
                $pdo,
                "ALTER TABLE research_proposals\n                 ADD COLUMN workflow_status VARCHAR(80) NULL DEFAULT 'DRAFT' AFTER progress_percentage"
            );
        }

        if (!createResearchColumnExists($pdo, 'research_proposals', 'workflow_stage')) {
            createResearchTryAlter(
                $pdo,
                "ALTER TABLE research_proposals\n                 ADD COLUMN workflow_stage VARCHAR(80) NULL DEFAULT 'SUBMISSION' AFTER workflow_status"
            );
        }

        if (!createResearchColumnExists($pdo, 'research_proposals', 'workflow_updated_at')) {
            createResearchTryAlter(
                $pdo,
                "ALTER TABLE research_proposals\n                 ADD COLUMN workflow_updated_at DATETIME NULL AFTER workflow_stage"
            );
        }

        if (!createResearchColumnExists($pdo, 'research_proposals', 'is_archived')) {
            createResearchTryAlter(
                $pdo,
                "ALTER TABLE research_proposals\n                 ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER workflow_updated_at"
            );
        }
    }

    if (
        createResearchTableExists($pdo, 'research_documents')
        &&
        createResearchColumnExists($pdo, 'research_documents', 'document_type')
    ) {
        createResearchTryAlter(
            $pdo,
            "ALTER TABLE research_documents\n             MODIFY COLUMN document_type VARCHAR(80) NOT NULL"
        );
    }
}

function createResearchGenerateManuscriptNumber(int $researchId): string
{
    return sprintf(
        'ASU-RES-%04d-%04d',
        (int)date('Y'),
        $researchId
    );
}

function createResearchDocumentTypeValue(
    PDO $pdo,
    string $preferredType,
    string $fallbackType = 'Proposal'
): string {

    try {
        $stmt =
            $pdo->prepare("\n                SELECT COLUMN_TYPE\n                FROM information_schema.COLUMNS\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = 'research_documents'\n                  AND COLUMN_NAME = 'document_type'\n                LIMIT 1\n            ");

        $stmt->execute();

        $columnType =
            (string)$stmt->fetchColumn();

        if (
            stripos(
                $columnType,
                'enum('
            ) === 0
        ) {
            $allowed = [];

            if (
                preg_match_all(
                    "/'((?:[^'\\\\]|\\\\.)*)'/",
                    $columnType,
                    $matches
                )
            ) {
                foreach ($matches[1] as $value) {
                    $allowed[] =
                        stripcslashes($value);
                }
            }

            if (in_array($preferredType, $allowed, true)) {
                return $preferredType;
            }

            if (in_array('Other', $allowed, true)) {
                return 'Other';
            }

            if (in_array($fallbackType, $allowed, true)) {
                return $fallbackType;
            }

            return $allowed[0] ?? $fallbackType;
        }
    } catch (Throwable $e) {
        error_log(
            'Create Research Document Type Check Warning: ' .
            $e->getMessage()
        );
    }

    return $preferredType;
}

try {
    createResearchEnsurePhaseOneSchema($pdo);
} catch (Throwable $e) {
    error_log(
        'Create Research Phase One Schema Error: ' .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| LOAD FACULTY INSTITUTIONAL ASSIGNMENT
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
    $user['user_id']
]);

$institutionalData =
    $userStmt->fetch(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| ACTIVE INSTITUTIONAL MASTER DATA
|--------------------------------------------------------------------------
| Both dropdowns share the Superadmin's colleges/departments tables.
| A department is selectable only while it and its parent college are Active.
| The faculty profile assignment is a default, not a mandatory restriction.
*/
$activeColleges = $pdo->query("
    SELECT college_id, college_name
    FROM colleges
    WHERE status = 'Active'
    ORDER BY college_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$activeDepartments = $pdo->query("
    SELECT d.department_id, d.college_id, d.department_name
    FROM departments d
    INNER JOIN colleges c ON c.college_id = d.college_id
    WHERE d.status = 'Active' AND c.status = 'Active'
    ORDER BY d.department_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$selectedCollegeId = (int)($institutionalData['college_id'] ?? 0);
$selectedDepartmentId = (int)($institutionalData['department_id'] ?? 0);

// Do not preselect an inactive, deleted, or mismatched assignment.
$activeCollegeIds = array_map('intval', array_column($activeColleges, 'college_id'));
if (!in_array($selectedCollegeId, $activeCollegeIds, true)) {
    $selectedCollegeId = 0;
}
$profileDepartmentValid = false;
foreach ($activeDepartments as $department) {
    if ((int)$department['department_id'] === $selectedDepartmentId
        && (int)$department['college_id'] === $selectedCollegeId) {
        $profileDepartmentValid = true;
        break;
    }
}
if (!$profileDepartmentValid) {
    $selectedDepartmentId = 0;
}

/*
|--------------------------------------------------------------------------
| DEFAULT FORM VALUES
|--------------------------------------------------------------------------
*/

$researchTitle = '';
$researchCategory = '';
$researchType = '';
$manuscriptLink = '';

$abstract = '';
$objectives = '';
$methodology = '';
$expectedOutput = '';

$submissionTrack = 'REGULAR';
$fundingSource = '';
$estimatedBudget = '';

$startDate = '';
$targetCompletionDate = '';

/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? ''
        )
    ) {
        $errors[] =
            'Invalid request. Please refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | FORM VALUES
    |--------------------------------------------------------------------------
    */

    $researchTitle =
        createResearchSafeText(
            $_POST['research_title'] ?? ''
        );

    $researchCategory =
        createResearchSafeText(
            $_POST['research_category'] ?? ''
        );

    $researchType =
        createResearchSafeText(
            $_POST['research_type'] ?? ''
        );

    $manuscriptLink =
        createResearchSafeText(
            $_POST['manuscript_link'] ?? ''
        );

    $abstract =
        createResearchSafeText(
            $_POST['abstract'] ?? ''
        );

    $objectives =
        createResearchSafeText(
            $_POST['objectives'] ?? ''
        );

    $methodology =
        createResearchSafeText(
            $_POST['methodology'] ?? ''
        );

    $expectedOutput =
        createResearchSafeText(
            $_POST['expected_output'] ?? ''
        );

    // Step 2: Draft metadata retains the legacy REGULAR default.
    // The researcher chooses the actual pathway after submission in view.php.
    $submissionTrack = 'REGULAR';

    $fundingSource =
        createResearchSafeText(
            $_POST['funding_source'] ?? ''
        );

    $estimatedBudget =
        createResearchSafeText(
            $_POST['estimated_budget'] ?? ''
        );

    $startDate =
        createResearchSafeText(
            $_POST['start_date'] ?? ''
        );

    $targetCompletionDate =
        createResearchSafeText(
            $_POST['target_completion_date'] ?? ''
        );

    // Selected values belong to this proposal; do not overwrite the user profile.
    $postedCollege = filter_var($_POST['college_id'] ?? null, FILTER_VALIDATE_INT);
    $postedDepartment = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
    $selectedCollegeId = $postedCollege !== false && $postedCollege > 0 ? $postedCollege : 0;
    $selectedDepartmentId = $postedDepartment !== false && $postedDepartment > 0 ? $postedDepartment : 0;

    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    // Revalidate both IDs against the currently Active master records.
    if ($selectedCollegeId === 0
        || !in_array($selectedCollegeId, $activeCollegeIds, true)) {
        $errors[] = 'Please select an active college from Institutional Assignment.';
    }

    $departmentMatchesCollege = false;
    foreach ($activeDepartments as $department) {
        if ((int)$department['department_id'] === $selectedDepartmentId
            && (int)$department['college_id'] === $selectedCollegeId) {
            $departmentMatchesCollege = true;
            break;
        }
    }
    if (!$departmentMatchesCollege) {
        $errors[] = 'Please select an active department under the selected college.';
    }

    if ($researchTitle === '') {
        $errors[] = 'Research title is required.';
    }

    if (mb_strlen($researchTitle) > 255) {
        $errors[] = 'Research title must not exceed 255 characters.';
    }

    if ($researchCategory === '') {
        $errors[] = 'Research category is required.';
    }

    if ($researchType === '') {
        $errors[] = 'Research type is required.';
    }

    if ($abstract === '') {
        $errors[] = 'Research abstract is required.';
    }

    if ($objectives === '') {
        $errors[] = 'Research objectives are required.';
    }

    if ($methodology === '') {
        $errors[] = 'Research methodology is required.';
    }

    if ($expectedOutput === '') {
        $errors[] = 'Expected output is required.';
    }

    $allowedTracks = [
        'REGULAR',
        'SELF_INITIATED'
    ];

    if (
        !in_array(
            $submissionTrack,
            $allowedTracks,
            true
        )
    ) {
        $errors[] = 'Please select a valid submission track.';
    }

    if ($fundingSource === '') {
        $errors[] = 'Funding source is required.';
    }

    if (mb_strlen($fundingSource) > 150) {
        $errors[] = 'Funding source must not exceed 150 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | BUDGET VALIDATION
    |--------------------------------------------------------------------------
    */

    $budgetValue = null;

    if ($estimatedBudget !== '') {
        if (!is_numeric($estimatedBudget)) {
            $errors[] = 'Estimated budget must be a valid number.';
        } elseif ((float)$estimatedBudget < 0) {
            $errors[] = 'Estimated budget cannot be negative.';
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
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $startDate !== ''
        &&
        $targetCompletionDate !== ''
    ) {
        $startTimestamp =
            strtotime($startDate);

        $completionTimestamp =
            strtotime($targetCompletionDate);

        if (
            $startTimestamp === false
            ||
            $completionTimestamp === false
        ) {
            $errors[] = 'Please enter valid research dates.';
        } elseif ($completionTimestamp < $startTimestamp) {
            $errors[] =
                'Target completion date cannot be earlier than the proposed start date.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FILE VALIDATION
    |--------------------------------------------------------------------------
    */

    // Only shareable HTTPS links are accepted for both documents.
    // External files are never downloaded by this page.
    foreach ([
        'Manuscript' => $manuscriptLink
    ] as $documentLabel => $documentUrl) {
        if ($documentUrl === '') {
            $errors[] = $documentLabel . ' link is required.';
            continue;
        }

        $urlParts = parse_url($documentUrl);

        if (
            strlen($documentUrl) > 255
            || !filter_var($documentUrl, FILTER_VALIDATE_URL)
            || !is_array($urlParts)
            || strtolower($urlParts['scheme'] ?? '') !== 'https'
            || empty($urlParts['host'])
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
        ) {
            $errors[] = 'Enter a valid HTTPS ' . strtolower($documentLabel) . ' link (up to 255 characters).';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE PROPOSAL
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $movedFiles = [];

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | TEMPORARY MANUSCRIPT NUMBER
            |--------------------------------------------------------------------------
            |
            | The final manuscript number uses the research ID, so we insert first
            | then update after lastInsertId(). This avoids sequence-table conflicts.
            */

            $manuscriptNo =
                'PENDING-' .
                date('YmdHis') .
                '-' .
                bin2hex(random_bytes(3));

            /*
            |--------------------------------------------------------------------------
            | INSERT RESEARCH PROPOSAL
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare("
                    INSERT INTO research_proposals
                    (
                        researcher_id,
                        college_id,
                        department_id,

                        manuscript_no,
                        submission_track,

                        research_title,
                        research_category,
                        research_type,

                        abstract,
                        objectives,
                        methodology,
                        expected_output,

                        funding_source,
                        estimated_budget,

                        start_date,
                        target_completion_date,

                        status,
                        progress_percentage,
                        workflow_status,
                        workflow_stage,
                        is_archived
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,

                        ?,
                        ?,

                        ?,
                        ?,
                        ?,

                        ?,
                        ?,
                        ?,
                        ?,

                        ?,
                        ?,

                        ?,
                        ?,

                        'Draft',
                        0,
                        'DRAFT',
                        'SUBMISSION',
                        0
                    )
                ");

            $stmt->execute([

                (int)$user['user_id'],

                $selectedCollegeId,

                $selectedDepartmentId,

                $manuscriptNo,
                $submissionTrack,

                $researchTitle,
                $researchCategory,
                $researchType,

                $abstract,
                $objectives,
                $methodology,
                $expectedOutput,

                $fundingSource,
                $budgetValue,

                $startDate !== ''
                    ? $startDate
                    : null,

                $targetCompletionDate !== ''
                    ? $targetCompletionDate
                    : null
            ]);

            /*
            |--------------------------------------------------------------------------
            | NEW RESEARCH ID
            |--------------------------------------------------------------------------
            */

            $researchId =
                (int)$pdo->lastInsertId();

            $manuscriptNo =
                createResearchGenerateManuscriptNumber(
                    $researchId
                );

            $updateManuscriptStmt =
                $pdo->prepare("
                    UPDATE research_proposals
                    SET manuscript_no = ?,
                        workflow_updated_at = NOW()
                    WHERE research_id = ?
                ");

            $updateManuscriptStmt->execute([
                $manuscriptNo,
                $researchId
            ]);

            /*
            |--------------------------------------------------------------------------
            | SAVE MANUSCRIPT LINK
            |--------------------------------------------------------------------------
            */

            // Store the manuscript URL in the existing document record.
            // The server does not fetch or download external documents.
            $proposalMoved = [
                'stored_name' => 'linked_manuscript_' . $researchId,
                'database_path' => $manuscriptLink
            ];

            $proposalDocument = [
                'original_name' => 'Manuscript Link',
                'extension' => 'url',
                'size' => 0
            ];

            /*
            |--------------------------------------------------------------------------
            | INSERT DOCUMENT RECORDS
            |--------------------------------------------------------------------------
            */

            $documentStmt =
                $pdo->prepare("
                    INSERT INTO research_documents
                    (
                        research_id,
                        uploaded_by,
                        document_type,

                        original_name,
                        stored_name,
                        file_path,

                        file_extension,
                        file_size
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,

                        ?,
                        ?,
                        ?,

                        ?,
                        ?
                    )
                ");

            $documentStmt->execute([

                $researchId,
                (int)$user['user_id'],
                createResearchDocumentTypeValue($pdo, 'Proposal'),

                $proposalDocument['original_name'],
                $proposalMoved['stored_name'],
                $proposalMoved['database_path'],

                $proposalDocument['extension'],
                $proposalDocument['size']
            ]);

            /*
            |--------------------------------------------------------------------------
            | CREATE STATUS HISTORY
            |--------------------------------------------------------------------------
            */

            $historyStmt =
                $pdo->prepare("
                    INSERT INTO research_status_history
                    (
                        research_id,
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

            $historyStmt->execute([

                $researchId,
                (int)$user['user_id'],
                'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ' . $manuscriptNo
            ]);

            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            setFlash(
                'success',
                'Research manuscript saved successfully. Manuscript No.: ' .
                $manuscriptNo .
                '. You may now submit it for RPS processing.'
            );

            redirect(
                BASE_URL .
                '/research/view.php?id=' .
                $researchId
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($movedFiles as $movedFile) {
                if (
                    $movedFile
                    &&
                    is_file($movedFile)
                ) {
                    @unlink($movedFile);
                }
            }

            error_log(
                'Create Research Manuscript Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to save the research manuscript. Please try again. The system already logged the exact database/upload error.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| LOAD LAYOUT
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';

?>

<style>

/* =========================================================
   UPLOAD MANUSCRIPT - PHASE 1
========================================================= */

.phase-wrapper {
    max-width: 1120px;
    margin: 0 auto;
}

.phase-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
    margin-bottom: 20px;
}

.phase-kicker {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #fff6d7;
    color: #12612d;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.phase-heading h1 {
    margin: 0 0 6px;
    color: #102416;
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -.02em;
}

.phase-heading p {
    max-width: 690px;
    margin: 0;
    color: #58625b;
    font-size: 12px;
    line-height: 1.65;
}

.research-form-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #dfe8dd;
    border-radius: 20px;
    box-shadow: 0 14px 36px rgba(22, 54, 29, .07);
}

.research-form-card-header {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 20px 24px;
    background:
        linear-gradient(
            135deg,
            #12612d 0%,
            #0b4722 100%
        );
    color: #ffffff;
}

.research-form-header-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: rgba(255, 212, 73, .18);
    color: #ffd449;
    font-size: 19px;
}

.research-form-card-header h2 {
    margin: 0 0 3px;
    font-size: 15px;
    font-weight: 800;
}

.research-form-card-header p {
    margin: 0;
    color: rgba(255, 255, 255, .78);
    font-size: 10px;
    line-height: 1.5;
}

.research-form-body {
    padding: 25px;
}

.research-form-section {
    margin-bottom: 28px;
}

.research-form-section:last-child {
    margin-bottom: 0;
}

.research-section-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #edf1ec;
    color: #111111;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .045em;
    text-transform: uppercase;
}

.research-section-title i {
    color: #12612d;
    font-size: 14px;
}

.research-form-card .form-label {
    margin-bottom: 6px;
    color: #1c251f;
    font-size: 11px;
    font-weight: 700;
}

.required-mark {
    color: #d8701f;
}

.research-form-card .form-control,
.research-form-card .form-select {
    min-height: 44px;
    border: 1px solid #d7e0d5;
    border-radius: 10px;
    background: #fbfcfb;
    color: #111111;
    font-size: 12px;
}

.research-form-card textarea.form-control {
    min-height: 112px;
    resize: vertical;
    line-height: 1.6;
}

.research-form-card .form-control:focus,
.research-form-card .form-select:focus {
    border-color: #12612d;
    background: #ffffff;
    box-shadow: 0 0 0 .16rem rgba(18, 97, 45, .10);
}

.research-field-note {
    display: block;
    margin-top: 5px;
    color: #788079;
    font-size: 9px;
    line-height: 1.5;
}

.research-assignment-box {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 14px;
    border: 1px solid #e1e8e1;
    border-radius: 12px;
    background: #f8faf8;
}

.research-assignment-item small {
    display: block;
    margin-bottom: 3px;
    color: #778079;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-weight: 800;
}

.research-assignment-item strong {
    display: block;
    color: #111111;
    font-size: 11px;
    font-weight: 700;
}

.research-upload-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.research-upload-box {
    position: relative;
    height: 100%;
    padding: 20px;
    border: 1px dashed #a7bda9;
    border-radius: 15px;
    background: #fbfcfb;
    text-align: center;
    transition:
        transform .16s ease,
        border-color .16s ease,
        background .16s ease,
        box-shadow .16s ease;
}

.research-upload-box:hover {
    transform: translateY(-2px);
    border-color: #12612d;
    background: #f8fbf8;
    box-shadow: 0 10px 24px rgba(18, 97, 45, .07);
}

.research-upload-icon {
    width: 46px;
    height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    border-radius: 14px;
    background: #12612d;
    color: #ffd449;
    font-size: 20px;
}

.research-upload-box h5 {
    margin: 0 0 5px;
    color: #111111;
    font-size: 12px;
    font-weight: 800;
}

.research-upload-box p {
    min-height: 44px;
    margin: 0 0 13px;
    color: #667069;
    font-size: 9.5px;
    line-height: 1.55;
}

.research-upload-box .form-control {
    background: #ffffff;
}

.upload-security-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 13px;
    padding: 11px 13px;
    border-radius: 10px;
    background: #fff8df;
    color: #66551a;
    font-size: 9.5px;
    line-height: 1.55;
}

.upload-security-note i {
    margin-top: 1px;
    color: #d8701f;
}

.research-form-errors {
    margin-bottom: 18px;
    padding: 14px 17px;
    border: 1px solid #f0d1ba;
    border-radius: 14px;
    background: #fff7ef;
    color: #7b3912;
    font-size: 11px;
}

.research-form-errors strong {
    display: block;
    margin-bottom: 7px;
}

.research-form-errors ul {
    margin: 0;
    padding-left: 19px;
}

.research-form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 13px;
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid #edf1ec;
}

.research-draft-note {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #667069;
    font-size: 10px;
    line-height: 1.45;
}

.research-draft-note i {
    color: #12612d;
}

.research-action-buttons {
    display: flex;
    gap: 9px;
}

@media (max-width: 768px) {
    .phase-heading {
        flex-direction: column;
    }

    .research-assignment-box {
        grid-template-columns: 1fr;
    }

    .research-form-body {
        padding: 19px;
    }

    .research-form-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .research-action-buttons {
        width: 100%;
        flex-direction: column-reverse;
    }

    .research-action-buttons .btn {
        width: 100%;
    }
}



/* =========================================================
   REFINED PHASE 1 LAYOUT
   - no top step/status cards
   - manuscript upload is primary
========================================================= */

.phase-heading {
    margin-bottom: 24px;
}

.phase-kicker {
    background: #fff7dc;
    color: #0b6b2a;
}

.research-upload-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.research-upload-grid .research-upload-box:first-child {
    min-height: 220px;
    padding: 34px 28px 30px;
    border-width: 1.5px;
    border-color: #9fbfa5;
    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #f8fbf8 100%
        );
}

.research-upload-grid .research-upload-box:first-child .research-upload-icon {
    width: 62px;
    height: 62px;
    margin-bottom: 13px;
    border-radius: 18px;
    font-size: 27px;
    box-shadow: 0 12px 24px rgba(18, 97, 45, .12);
}

.research-upload-grid .research-upload-box:first-child h5 {
    margin-bottom: 7px;
    font-size: 16px;
}

.research-upload-grid .research-upload-box:first-child p {
    min-height: 0;
    margin-bottom: 18px;
    font-size: 11px;
}

.research-upload-grid .research-upload-box:first-child .form-control {
    max-width: 700px;
    margin: 0 auto;
}

.research-upload-grid .research-upload-box:nth-child(2) {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: auto;
    padding: 14px 16px;
    border-style: solid;
    border-color: #eadcaa;
    background: #fffdf4;
    text-align: left;
}

.research-upload-grid .research-upload-box:nth-child(2):hover {
    transform: translateY(-1px);
    border-color: #e1bd43;
    background: #fffaf0;
    box-shadow: 0 8px 18px rgba(216, 112, 31, .06);
}

.research-upload-grid .research-upload-box:nth-child(2) .research-upload-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    margin: 0;
    border-radius: 12px;
    background: #fff1bf;
    color: #0b6b2a;
    font-size: 17px;
}

.research-upload-grid .research-upload-box:nth-child(2) h5 {
    margin: 0 0 3px;
    font-size: 11.5px;
}

.research-upload-grid .research-upload-box:nth-child(2) p {
    min-height: 0;
    margin: 0;
    color: #756d53;
    font-size: 9px;
    line-height: 1.4;
}

.research-upload-grid .research-upload-box:nth-child(2) .form-control {
    width: 43%;
    min-width: 320px;
    margin-left: auto;
    background: #ffffff;
}

.upload-security-note {
    margin-top: 12px;
}

@media (max-width: 992px) {
    .research-upload-grid .research-upload-box:nth-child(2) {
        align-items: stretch;
        flex-direction: column;
        text-align: center;
    }

    .research-upload-grid .research-upload-box:nth-child(2) .research-upload-icon {
        margin: 0 auto;
    }

    .research-upload-grid .research-upload-box:nth-child(2) .form-control {
        width: 100%;
        min-width: 0;
        margin-left: 0;
    }
}


/* =========================================================
   UPLOAD ORDER FIX
   - manuscript upload stays large and more important
========================================================= */

.research-upload-grid.upload-layout-reordered {
    display: grid;
    grid-template-columns: 1fr;
    gap: 13px;
}







.research-upload-grid.upload-layout-reordered .manuscript-upload-box {
    display: block;
    min-height: 225px;
    padding: 36px 28px 31px;
    border: 1.5px dashed #9fbfa5;
    border-radius: 15px;
    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #f8fbf8 100%
        );
    text-align: center;
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box:hover {
    transform: translateY(-2px);
    border-color: #12612d;
    background: #f8fbf8;
    box-shadow: 0 10px 24px rgba(18, 97, 45, .07);
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box .research-upload-icon {
    width: 62px;
    height: 62px;
    min-width: 62px;
    margin: 0 auto 13px;
    border-radius: 18px;
    background: #12612d;
    color: #ffd449;
    font-size: 27px;
    box-shadow: 0 12px 24px rgba(18, 97, 45, .12);
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box h5 {
    margin: 0 0 7px;
    font-size: 16px;
    font-weight: 800;
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box p {
    min-height: 0;
    margin: 0 0 18px;
    color: #667069;
    font-size: 11px;
    line-height: 1.55;
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box .form-control {
    width: 100%;
    max-width: 700px;
    margin: 0 auto;
    background: #ffffff;
}

@media (max-width: 992px) {


}



/* =========================================================
   FINAL UPLOAD MANUSCRIPT DESIGN
   Subpage style — no large hero/banner
========================================================= */

:root {
    --asu-green: #007a33;
    --asu-green-dark: #005c26;
    --asu-green-deep: #003f1a;
    --asu-yellow: #ffd51f;
    --asu-orange: #f28c28;
    --asu-ink: #101810;
    --asu-muted: #657068;
}

/* ---------------------------------------------------------
   GLOBAL / BACKGROUND
--------------------------------------------------------- */

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
    background:
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

.upload-manuscript-page {
    min-height: calc(100vh - 76px) !important;
    padding: 24px 28px 30px !important;
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

.phase-wrapper {
    max-width: 1120px !important;
    margin: 0 auto !important;
}

/* ---------------------------------------------------------
   TOPBAR
--------------------------------------------------------- */

.asu-topbar {
    min-height: 76px !important;
    padding: 0 30px !important;
    background: rgba(255,255,255,.97) !important;
    border-bottom: 1px solid rgba(0,122,51,.12) !important;
    box-shadow: 0 10px 26px rgba(16,24,16,.035) !important;
}

.topbar-left {
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
}

.system-name {
    display: none !important;
}

.system-brand-copy {
    position: relative !important;
    padding-left: 18px !important;
    line-height: 1.15 !important;
}

.system-brand-copy::before {
    content: "" !important;
    position: absolute !important;
    left: 0 !important;
    top: 50% !important;
    width: 3px !important;
    height: 38px !important;
    transform: translateY(-50%) !important;
    border-radius: 999px !important;
    background: var(--asu-green) !important;
}

.system-brand-copy strong {
    display: block !important;
    color: var(--asu-ink) !important;
    font-size: 17px !important;
    font-weight: 800 !important;
    letter-spacing: -.015em !important;
}

.system-brand-copy span {
    display: block !important;
    margin-top: 4px !important;
    color: var(--asu-muted) !important;
    font-size: 13px !important;
    font-weight: 400 !important;
}

.topbar-user-text strong {
    font-size: 15px !important;
    line-height: 1.1 !important;
}

.topbar-user-text span {
    margin-top: 4px !important;
    color: var(--asu-muted) !important;
    font-size: 12px !important;
}

.asu-topbar .user-avatar {
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    border-radius: 50% !important;
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;
}

/* ---------------------------------------------------------
   SIMPLE SUBPAGE HEADING
--------------------------------------------------------- */

.phase-heading {
    display: flex !important;
    justify-content: space-between !important;
    align-items: flex-start !important;
    gap: 18px !important;
    margin: 0 0 18px !important;
    padding: 4px 2px 0 !important;
}

.phase-heading > div {
    min-width: 0 !important;
}

.phase-kicker {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    margin: 0 0 8px !important;
    padding: 5px 9px !important;
    border: 1px solid rgba(0,122,51,.12) !important;
    border-radius: 999px !important;
    background: rgba(255,255,255,.76) !important;
    color: var(--asu-green) !important;
    font-size: 8.5px !important;
    font-weight: 800 !important;
    letter-spacing: .04em !important;
    text-transform: uppercase !important;
}

.phase-heading h1 {
    margin: 0 0 7px !important;
    color: var(--asu-ink) !important;
    font-size: 28px !important;
    line-height: 1.15 !important;
    font-weight: 900 !important;
    letter-spacing: -.03em !important;
}

.phase-heading p {
    max-width: 720px !important;
    margin: 0 !important;
    color: #707a73 !important;
    font-size: 10.5px !important;
    line-height: 1.55 !important;
}

.phase-heading > .btn {
    min-height: 36px !important;
    padding: 0 11px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    border: 1px solid rgba(0,122,51,.20) !important;
    border-radius: 9px !important;
    background: rgba(255,255,255,.88) !important;
    color: var(--asu-green) !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
    box-shadow: 0 6px 14px rgba(16,24,16,.025) !important;
}

.phase-heading > .btn:hover {
    background: #f2f8f3 !important;
    color: var(--asu-green-dark) !important;
}

/* ---------------------------------------------------------
   ERROR BOX
--------------------------------------------------------- */

.research-form-errors {
    margin-bottom: 16px !important;
    padding: 13px 15px !important;
    border: 1px solid rgba(242,140,40,.26) !important;
    border-left: 4px solid var(--asu-orange) !important;
    border-radius: 12px !important;
    background: #fff9f3 !important;
    color: #7c4516 !important;
    font-size: 10px !important;
    box-shadow: 0 8px 20px rgba(16,24,16,.03) !important;
}

.research-form-errors strong {
    margin-bottom: 6px !important;
    font-size: 10px !important;
}

/* ---------------------------------------------------------
   FORM CARD
--------------------------------------------------------- */

.research-form-card {
    overflow: hidden !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 16px 38px rgba(16,24,16,.06) !important;
}

.research-form-card-header {
    min-height: 68px !important;
    padding: 14px 18px !important;
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    border-bottom: 1px solid rgba(0,122,51,.09) !important;
    background: rgba(255,255,255,.88) !important;
    color: var(--asu-ink) !important;
}

.research-form-header-icon {
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    border-radius: 11px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
    font-size: 18px !important;
}

.research-form-card-header h2 {
    margin: 0 0 3px !important;
    color: var(--asu-ink) !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;
}

.research-form-card-header p {
    margin: 0 !important;
    color: var(--asu-muted) !important;
    font-size: 9px !important;
    line-height: 1.45 !important;
}

.research-form-body {
    padding: 20px !important;
}

/* ---------------------------------------------------------
   FORM SECTIONS
--------------------------------------------------------- */

.research-form-section {
    margin-bottom: 24px !important;
}

.research-form-section:last-child {
    margin-bottom: 0 !important;
}

.research-section-title {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin-bottom: 14px !important;
    padding-bottom: 9px !important;
    border-bottom: 1px solid rgba(0,122,51,.08) !important;
    color: #283229 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    letter-spacing: .03em !important;
    text-transform: uppercase !important;
}

.research-section-title i {
    color: var(--asu-green) !important;
    font-size: 13px !important;
}

.research-form-card .form-label {
    margin-bottom: 5px !important;
    color: #263229 !important;
    font-size: 10px !important;
    font-weight: 700 !important;
}

.required-mark {
    color: #d56800 !important;
}

/* ---------------------------------------------------------
   INPUTS
--------------------------------------------------------- */

.research-form-card .form-control,
.research-form-card .form-select,
.research-form-card .input-group-text {
    min-height: 42px !important;
    border-color: #d7e0d8 !important;
    background: #ffffff !important;
    color: #263229 !important;
    font-size: 10.5px !important;
    box-shadow: none !important;
}

.research-form-card .form-control,
.research-form-card .form-select {
    border-radius: 10px !important;
}

.research-form-card textarea.form-control {
    min-height: 108px !important;
    line-height: 1.6 !important;
    resize: vertical !important;
}

.research-form-card .form-control:focus,
.research-form-card .form-select:focus {
    border-color: #76ac82 !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;
}

.research-form-card .input-group-text {
    border-radius: 10px 0 0 10px !important;
}

.research-form-card .input-group .form-control {
    border-radius: 0 10px 10px 0 !important;
}

.research-form-card input[disabled] {
    background: #f5f7f5 !important;
    color: #6d766f !important;
}

.research-field-note {
    margin-top: 5px !important;
    color: #818b84 !important;
    font-size: 8.5px !important;
    line-height: 1.45 !important;
}

/* ---------------------------------------------------------
   INSTITUTIONAL ASSIGNMENT
--------------------------------------------------------- */

.research-assignment-box {
    min-height: 42px !important;
    padding: 9px 11px !important;
    gap: 10px !important;
    border: 1px solid rgba(0,122,51,.09) !important;
    border-radius: 10px !important;
    background: #f8faf8 !important;
}

.research-assignment-item small {
    margin-bottom: 2px !important;
    color: #7d8780 !important;
    font-size: 7.8px !important;
}

.research-assignment-item strong {
    color: #263229 !important;
    font-size: 9.5px !important;
    font-weight: 700 !important;
}

/* ---------------------------------------------------------
   UPLOADS — MANUSCRIPT PRIMARY
--------------------------------------------------------- */

.research-upload-grid.upload-layout-reordered {
    gap: 13px !important;
}







.research-upload-grid.upload-layout-reordered .manuscript-upload-box {
    min-height: 210px !important;
    padding: 31px 26px 28px !important;
    border: 1.5px dashed rgba(0,122,51,.35) !important;
    border-radius: 15px !important;
    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #f8fbf8 100%
        ) !important;
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box:hover {
    transform: translateY(-2px) !important;
    border-color: var(--asu-green) !important;
    background: #f8fbf8 !important;
    box-shadow: 0 10px 24px rgba(0,122,51,.07) !important;
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box .research-upload-icon {
    width: 58px !important;
    height: 58px !important;
    min-width: 58px !important;
    margin: 0 auto 12px !important;
    border-radius: 16px !important;
    background:
        linear-gradient(
            135deg,
            var(--asu-green),
            var(--asu-green-dark)
        ) !important;
    color: #ffffff !important;
    font-size: 24px !important;
    box-shadow: 0 10px 20px rgba(0,122,51,.18) !important;
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box h5 {
    margin: 0 0 6px !important;
    color: var(--asu-ink) !important;
    font-size: 14px !important;
    font-weight: 800 !important;
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box p {
    margin: 0 0 16px !important;
    color: var(--asu-muted) !important;
    font-size: 9.5px !important;
    line-height: 1.5 !important;
}

.research-upload-grid.upload-layout-reordered .manuscript-upload-box .form-control {
    width: 100% !important;
    max-width: 700px !important;
    margin: 0 auto !important;
    background: #ffffff !important;
}

/* ---------------------------------------------------------
   SECURITY NOTE
--------------------------------------------------------- */

.upload-security-note {
    margin-top: 11px !important;
    padding: 10px 12px !important;
    border: 1px solid rgba(0,122,51,.08) !important;
    border-radius: 10px !important;
    background: #f7faf7 !important;
    color: #657068 !important;
    font-size: 8.5px !important;
    line-height: 1.5 !important;
}

.upload-security-note i {
    color: var(--asu-green) !important;
}

/* ---------------------------------------------------------
   ACTIONS
--------------------------------------------------------- */

.research-form-actions {
    margin-top: 24px !important;
    padding-top: 17px !important;
    border-top: 1px solid rgba(0,122,51,.08) !important;
}

.research-draft-note {
    color: #657068 !important;
    font-size: 9px !important;
    line-height: 1.45 !important;
}

.research-draft-note i {
    color: var(--asu-green) !important;
}

.research-action-buttons {
    gap: 9px !important;
}

.research-action-buttons .btn {
    min-height: 42px !important;
    padding: 0 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 10px !important;
    font-size: 10px !important;
    font-weight: 800 !important;
}

.research-action-buttons .btn-outline-asu {
    border: 1px solid rgba(0,122,51,.20) !important;
    background: #ffffff !important;
    color: var(--asu-green) !important;
}

.research-action-buttons .btn-outline-asu:hover {
    background: #f2f8f3 !important;
    color: var(--asu-green-dark) !important;
}

.research-action-buttons .btn-asu {
    border: 1px solid var(--asu-green) !important;
    background:
        linear-gradient(
            135deg,
            var(--asu-green),
            var(--asu-green-dark)
        ) !important;
    color: #ffffff !important;
    box-shadow: 0 8px 18px rgba(0,122,51,.14) !important;
}

.research-action-buttons .btn-asu:hover {
    background: var(--asu-green-dark) !important;
    color: #ffffff !important;
}

/* Common/shared footer only */
.content-footer {
    display: none !important;
}

/* ---------------------------------------------------------
   RESPONSIVE
--------------------------------------------------------- */

@media (max-width: 992px) {


}

@media (max-width: 760px) {
    .asu-topbar {
        padding: 0 16px !important;
    }

    .system-brand-copy strong {
        font-size: 13px !important;
    }

    .system-brand-copy span {
        font-size: 10px !important;
    }

    .upload-manuscript-page {
        padding: 18px 14px 24px !important;
    }

    .phase-heading {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .phase-heading h1 {
        font-size: 24px !important;
    }

    .phase-heading > .btn {
        align-self: flex-start !important;
    }

    .research-form-body {
        padding: 16px !important;
    }

    .research-assignment-box {
        grid-template-columns: 1fr !important;
    }

    .research-form-actions {
        align-items: stretch !important;
        flex-direction: column !important;
    }

    .research-action-buttons {
        width: 100% !important;
        flex-direction: column-reverse !important;
    }

    .research-action-buttons .btn {
        width: 100% !important;
    }
}



/* =========================================================
   CREATE.PHP — TOP SECTION ONLY
   Match the Project Monitoring / Research Details banner.
   No form, upload, validation, workflow, or database styles changed.
========================================================= */

.phase-heading {
    position: relative !important;
    overflow: hidden !important;

    min-height: 96px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 18px !important;

    margin: 0 0 20px !important;
    padding: 20px 28px !important;

    border: 0 !important;
    border-radius: 18px !important;

    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #2c9a39 100%
        ) !important;

    box-shadow: 0 18px 42px rgba(0,90,40,.16) !important;
}

.phase-heading::after {
    content: "" !important;

    position: absolute !important;
    right: -110px !important;
    top: -120px !important;

    width: 430px !important;
    height: 280px !important;

    transform: rotate(-9deg) !important;
    border-radius: 42px !important;

    background:
        linear-gradient(
            135deg,
            rgba(255,213,31,.16),
            rgba(255,255,255,.035)
        ) !important;

    pointer-events: none !important;
}

.phase-heading > * {
    position: relative !important;
    z-index: 2 !important;
}

.phase-kicker {
    margin: 0 0 8px !important;

    border: 1px solid rgba(255,255,255,.22) !important;
    background: rgba(255,255,255,.12) !important;
    color: #ffffff !important;
}

.phase-kicker i {
    color: #ffd51f !important;
}

.phase-heading h1 {
    margin: 0 0 5px !important;

    color: #ffffff !important;

    font-size: 30px !important;
    font-weight: 900 !important;
    line-height: 1.15 !important;
    letter-spacing: -.025em !important;
}

.phase-heading p {
    max-width: 760px !important;
    margin: 0 !important;

    color: rgba(255,255,255,.80) !important;

    font-size: 10.5px !important;
    line-height: 1.55 !important;
}

/* Back to Research — exact New Proposal button colors */
.phase-heading > .btn,
.phase-heading > .btn.btn-outline-asu {
    min-height: 42px !important;
    height: 42px !important;
    padding: 0 14px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;

    border: 0 !important;
    border-radius: 10px !important;

    background: #ffd51f !important;
    color: #003f1a !important;

    font-size: 10.5px !important;
    font-weight: 800 !important;

    box-shadow: 0 8px 18px rgba(255,213,31,.18) !important;
}

.phase-heading > .btn i,
.phase-heading > .btn.btn-outline-asu i {
    color: #003f1a !important;
}

.phase-heading > .btn:hover,
.phase-heading > .btn.btn-outline-asu:hover {
    background: #f0bd00 !important;
    color: #003f1a !important;
}

.phase-heading > .btn:hover i,
.phase-heading > .btn.btn-outline-asu:hover i {
    color: #003f1a !important;
}

@media (max-width: 760px) {
    .phase-heading {
        min-height: 0 !important;
        align-items: flex-start !important;
        flex-direction: column !important;
        padding: 18px !important;
    }

    .phase-heading h1 {
        font-size: 25px !important;
    }

    .phase-heading > .btn,
    .phase-heading > .btn.btn-outline-asu {
        align-self: flex-start !important;
    }
}


/* =========================================================
   MANUSCRIPT LINK — COMPACT ROW
   Presentation only; no other form sections are affected.
========================================================= */
.research-upload-grid.upload-layout-reordered .research-upload-box.upload-link-box {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 16px !important;
    width: 100% !important;
    min-height: 88px !important;
    height: auto !important;
    padding: 14px 18px !important;
    border-radius: 15px !important;
    text-align: left !important;
    box-shadow: none !important;
    transform: none !important;
}
.research-upload-grid.upload-layout-reordered .manuscript-upload-box.upload-link-box {
    border: 1px solid rgba(0,122,51,.30) !important;
    background: #f9fcfa !important;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-info {
    display: flex;
    flex: 0 0 34%;
    align-items: center;
    gap: 13px;
    min-width: 0;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .research-upload-icon {
    width: 42px !important;
    height: 42px !important;
    min-width: 42px !important;
    margin: 0 !important;
    border-radius: 12px !important;
    background: #edf7ef !important;
    color: #007a33 !important;
    font-size: 18px !important;
    box-shadow: none !important;
}
.research-upload-grid.upload-layout-reordered .upload-link-box h5 {
    margin: 0 0 4px !important;
    font-size: 12px !important;
    color: #263229 !important;
    font-weight: 800 !important;
}
.research-upload-grid.upload-layout-reordered .upload-link-box p {
    margin: 0 !important;
    min-height: 0 !important;
    font-size: 9.5px !important;
    color: #68766c !important;
    line-height: 1.4 !important;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-controls {
    display: flex;
    flex: 1 1 auto;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-controls .form-control {
    flex: 1 1 auto;
    width: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
    height: 44px !important;
    margin: 0 !important;
    background: #fff !important;
    font-size: 11px !important;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-view {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    margin: 0;
    padding: 0 14px;
    border: 1px solid #bad6c0;
    border-radius: 10px;
    background: #fff;
    color: #007a33;
    font-size: 11px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-view:not(.is-disabled):hover {
    background: #edf7ef;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-view.is-disabled {
    opacity: .55;
    cursor: not-allowed;
}
@media (max-width: 900px) {
    .research-upload-grid.upload-layout-reordered .research-upload-box.upload-link-box {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
    }
    .research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-info {
        flex-basis: auto;
    }
}
/* A single, shared confirmation beneath Manuscript Link.
   It acknowledges the Manuscript. */
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-body {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-width: 0;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-controls {
    width: 100%;
    flex-wrap: wrap;
}
.research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-controls .form-control {
    flex: 1 1 260px;
}
.shared-document-review {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e0ebe2;
}
.shared-document-review-label {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: #415448;
    font-size: 11px;
    line-height: 1.5;
    cursor: pointer;
}
.shared-document-review-label input {
    width: 16px;
    height: 16px;
    margin: 0;
    flex: 0 0 16px;
    accent-color: #087b37;
    cursor: pointer;
}
.shared-document-review-label input:disabled {
    cursor: not-allowed;
}
.shared-review-confirm {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid #087b37;
    border-radius: 10px;
    background: #087b37;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
    cursor: pointer;
}
.shared-review-confirm:disabled {
    border-color: #c1d6c6;
    background: #daeae0;
    color: #6c8b76;
    cursor: not-allowed;
}
.shared-review-confirm.is-confirmed {
    border-color: #b8dbc3;
    background: #e8f6eb;
    color: #08622d;
}

.upload-link-icon-btn {
    min-width: 54px;
    width: 54px;
    padding: 0 !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.upload-link-icon-btn i {
    font-size: 18px;
    margin: 0 !important;
}

/* Single shared acknowledgement: checkbox and confirm inside the same note box. */
.upload-security-note.shared-acknowledgment-note {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 12px 14px !important;
    background: #f5faf6;
    border: 1px solid #e1ebe4;
    color: #415448;
}
.upload-security-note.shared-acknowledgment-note .shared-document-review-label {
    flex: 1 1 280px;
    min-width: 0;
    font-size: 11px;
    color: #415448;
}
.upload-security-note.shared-acknowledgment-note .shared-review-confirm {
    margin-left: auto;
}
@media (max-width: 900px) {
    .research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-body {
        width: 100%;
    }
}
@media (max-width: 600px) {
    .research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-controls .form-control {
        flex-basis: 100%;
    }
    .research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-view {
        flex: 1 1 auto;
    }
    .shared-document-review {
        align-items: stretch;
        flex-direction: column;
    }
    .shared-review-confirm {
        align-self: stretch;
    }
}


/* Funding & Schedule — consistent 2-column rows and field alignment. */
.asu-funding-schedule-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-areas:
        "funding start"
        "budget target";
    column-gap: 20px;
    row-gap: 20px;
    align-items: start;
}
.asu-fs-funding { grid-area: funding; }
.asu-fs-budget { grid-area: budget; }
.asu-fs-start { grid-area: start; }
.asu-fs-target { grid-area: target; }
.asu-funding-schedule-grid .asu-fs-field {
    min-width: 0;
}
.asu-funding-schedule-grid .asu-fs-field > .form-label {
    display: block;
    margin-bottom: 6px !important;
    line-height: 18px;
}
.asu-funding-schedule-grid .asu-fs-field > .form-control,
.asu-funding-schedule-grid .asu-fs-field > .input-group {
    width: 100%;
}
.asu-funding-schedule-grid .asu-fs-field .form-control,
.asu-funding-schedule-grid .asu-fs-field .input-group-text {
    height: 44px !important;
    min-height: 44px !important;
}
.asu-funding-schedule-grid .asu-fs-field > .research-field-note {
    margin-top: 5px;
}
@media (max-width: 767.98px) {
    .asu-funding-schedule-grid {
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas: "funding" "budget" "start" "target";
        row-gap: 17px;
    }
}

/* Green confirmation text only after the manuscript link is opened. */
#manuscriptViewMessage.is-link-opened {
    color: #007a33 !important;
    font-weight: 700 !important;
}


/* Upload reminder info icon — remains visible when JavaScript updates note text. */
.rems-upload-info-note {
    display: flex !important;
    align-items: flex-start;
    gap: 6px;
}
.rems-upload-info-note::before {
    content: "\f431";
    font-family: "bootstrap-icons" !important;
    font-style: normal;
    font-weight: normal;
    font-size: 13px;
    line-height: 1.2;
    color: #007a33 !important;
    flex: 0 0 auto;
}

/* Research Category and Research Type: show visible dropdown chevrons. */
.research-form-card #research_category,
.research-form-card #research_type,
.research-form-card #college_id,
.research-form-card #department_id {
    -webkit-appearance: none !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23007632' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 16px center !important;
    background-size: 16px 16px !important;
    padding-right: 44px !important;
}


/* Institutional Assignment: retain the same compact two-column presentation. */
.research-assignment-item { min-width: 0; }
.research-assignment-item .form-label { display: block; margin-bottom: 5px !important; }
.research-assignment-item .form-select { width: 100%; min-width: 0; }
.research-assignment-item .form-select:disabled { opacity: .65; cursor: not-allowed; }

/* =========================================================
   ASU-REMS UPLOAD RESEARCH MANUSCRIPT — DARK MODE
   Scope: research/create.php only.
   CSS ONLY: all PHP, form validation and preview gating unchanged.
   Honors includes/rems_theme.php's data-rems-theme setting.
========================================================= */

/* Keep the same dark canvas and header treatment as Research Management. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.upload-manuscript-page),
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page {
    background: #0b1d15 !important;
    color: #e9f2e9 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.upload-manuscript-page) > header.asu-topbar {
    background: #12291e !important;
    border-bottom-color: #365743 !important;
    box-shadow: 0 6px 18px rgba(0, 0, 0, .13) !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.upload-manuscript-page) .system-brand-copy strong,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.upload-manuscript-page) .topbar-user-text strong {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.upload-manuscript-page) .system-brand-copy span,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.upload-manuscript-page) .topbar-user-text span {
    color: #bfd0c1 !important;
}

/* Preserve the university's green-and-gold page banner. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .phase-heading {
    background: linear-gradient(112deg, #004b26 0%, #006b34 64%, #167c3c 100%) !important;
    box-shadow: 0 14px 28px rgba(0, 0, 0, .12) !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .phase-heading h1,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .phase-heading p {
    color: #ffffff !important;
}
/* Back button stays yellow with dark lettering, just like the light design. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .phase-heading > .btn,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .phase-heading > .btn i {
    color: #003f1a !important;
}

/* Main document card, header, section dividers and copy. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card {
    background: #152f23 !important;
    border-color: #365744 !important;
    color: #e9f2e9 !important;
    box-shadow: 0 14px 34px rgba(0, 0, 0, .13) !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card-header {
    background: #1b3a2a !important;
    border-bottom-color: #42624d !important;
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card-header h2 {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-header-icon {
    background: #27513b !important;
    color: #b4edbd !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-section-title {
    border-bottom-color: #365744 !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-section-title i {
    color: #85dca0 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .form-label {
    color: #e0eee2 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .required-mark {
    color: #f4b475 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-field-note,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-draft-note {
    color: #bdd0c0 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-draft-note i,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .rems-upload-info-note::before {
    color: #8fe0a8 !important;
}

/* Form values: sufficient contrast in normal, focus, disabled and placeholder states. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .form-control,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .form-select,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .input-group-text {
    background-color: #1e3c2d !important;
    border-color: #4c7058 !important;
    color: #f1f8f0 !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .form-control::placeholder,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card textarea::placeholder {
    color: #b7cdbb !important;
    opacity: 1 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card input[disabled] {
    background-color: #244032 !important;
    border-color: #486552 !important;
    color: #e1eee2 !important;
    -webkit-text-fill-color: #e1eee2;
    opacity: 1;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .form-control:focus,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .form-select:focus {
    background-color: #234634 !important;
    border-color: #79b58b !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 .16rem rgba(130, 220, 159, .15) !important;
}
/* The existing custom category/type chevrons are dark green (for light mode).
   Swap only their icon image so the arrows remain visible on dark selects. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card #research_category,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card #research_type,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card #college_id,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card #department_id {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23cbe8d1' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 16px center !important;
    background-size: 16px 16px !important;
}

/* Institutional assignment box (college, department). */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-assignment-box {
    background: #203e2e !important;
    border-color: #486a54 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-assignment-item small {
    color: #bad0bf !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-assignment-item strong {
    color: #eff7ee !important;
}

/* Manuscript link: compact row, eye preview, helper note and confirmation. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-grid.upload-layout-reordered .manuscript-upload-box.upload-link-box,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-box {
    background: #1b382a !important;
    border-color: #496d54 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-grid.upload-layout-reordered .manuscript-upload-box.upload-link-box:hover {
    background: #214432 !important;
    border-color: #79b58b !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-grid.upload-layout-reordered .upload-link-box h5,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-box h5 {
    color: #eff7ef !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-grid.upload-layout-reordered .upload-link-box p,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-box p {
    color: #bed1c1 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-grid.upload-layout-reordered .upload-link-box .research-upload-icon {
    background: #2a5038 !important;
    color: #a9edbd !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-controls .form-control {
    background-color: #1e3c2d !important;
    border-color: #4c7058 !important;
    color: #f1f8f0 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-view {
    background: #2a5038 !important;
    border-color: #60866b !important;
    color: #e8f8eb !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-upload-grid.upload-layout-reordered .upload-link-box .upload-link-view:not(.is-disabled):hover {
    background: #346346 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page #manuscriptViewMessage.is-link-opened {
    color: #9ce4af !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .upload-security-note,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .upload-security-note.shared-acknowledgment-note {
    background: #253e2d !important;
    border-color: #536f52 !important;
    color: #d6e6d8 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .shared-document-review-label,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .upload-security-note.shared-acknowledgment-note .shared-document-review-label {
    color: #d9e8db !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .shared-document-review {
    border-top-color: #496b54 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .shared-review-confirm:disabled {
    background: #33513d !important;
    border-color: #55735c !important;
    color: #c5d7c7 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .shared-review-confirm.is-confirmed {
    background: #235b36 !important;
    border-color: #629a75 !important;
    color: #ebffef !important;
}

/* Draft note, action row, outline buttons and readable validation errors. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-actions {
    border-top-color: #3b5b46 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-action-buttons .btn-outline-asu {
    background: #274b35 !important;
    border-color: #62836a !important;
    color: #e7f7e9 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-action-buttons .btn-outline-asu:hover {
    background: #326344 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-action-buttons .btn-asu:disabled {
    opacity: .65;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-errors {
    background: #463121 !important;
    border-color: #ae7745 !important;
    border-left-color: #e8a55c !important;
    color: #ffe9d1 !important;
}


/* Institutional Assignment: wider dropdowns, labels OUTSIDE individual fields. */
.research-form-card .research-assignment-box {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 14px !important;
    height: auto !important;
    min-height: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    align-items: start !important;
}
.research-form-card .research-assignment-item {
    position: static !important;
    height: auto !important;
    min-width: 0 !important;
}
.research-form-card .research-assignment-item:first-child::after {
    display: none !important;
}
.research-form-card .research-assignment-item .form-label {
    display: block !important;
    position: static !important;
    margin: 0 0 6px !important;
    font-size: 10px !important;
    line-height: 1.25 !important;
    pointer-events: auto !important;
}
.research-form-card .research-assignment-item .form-select {
    width: 100% !important;
    height: 48px !important;
    min-height: 48px !important;
    padding: 0 36px 0 12px !important;
    border: 1px solid #d7e0d8 !important;
    border-radius: 10px !important;
    background-color: #ffffff !important;
    background-position: right 12px center !important;
    box-shadow: none !important;
    font-size: 10.5px !important;
    text-overflow: ellipsis;
}
.research-form-card .research-assignment-item .form-select:focus {
    border-color: #76ac82 !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;
}
.research-form-card .research-assignment-box:focus-within {
    border: 0 !important;
    box-shadow: none !important;
}
/* Preserve the native ASU-REMS dark palette and visible chevrons. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .research-assignment-box {
    background: transparent !important;
    border: 0 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .research-assignment-item .form-select {
    background-color: #203e2e !important;
    border: 1px solid #486a54 !important;
    color: #e1eee2 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .upload-manuscript-page .research-form-card .research-assignment-item .form-select:focus {
    background-color: #234634 !important;
    border-color: #79b58b !important;
    box-shadow: 0 0 0 .16rem rgba(130,220,159,.15) !important;
}
@media (max-width: 760px) {
    .research-form-card .research-assignment-box {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
}

/* Researcher Information: equal label baselines and equal input heights. */
.research-form-card .researcher-information-fields > [class*="col-"] {
    display: flex !important;
    flex-direction: column !important;
    min-width: 0;
}
.research-form-card .researcher-information-fields .form-label,
.research-form-card .researcher-information-fields .research-assignment-item .form-label {
    display: block !important;
    height: 18px !important;
    min-height: 18px !important;
    margin: 0 0 6px !important;
    padding: 0 !important;
    line-height: 18px !important;
    font-size: 10px !important;
}
.research-form-card .researcher-information-fields .form-control,
.research-form-card .researcher-information-fields .form-select {
    width: 100% !important;
    height: 44px !important;
    min-height: 44px !important;
    box-sizing: border-box !important;
    margin: 0 !important;
}

</style>

<div class="main-page">

    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="asu-topbar">

        <div class="topbar-left">
            <button
                id="sidebarToggle"
                type="button"
                class="sidebar-toggle"
                aria-label="Open navigation"
            >
                <i class="bi bi-list"></i>
            </button>

            <div class="system-brand-copy">
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

    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <main class="page-content upload-manuscript-page">

        <div class="phase-wrapper">

            <!-- =================================================
                 HEADING
            ================================================== -->

            <div class="phase-heading">

                <div>
                

                    <h1>
                        Upload Research Manuscript
                    </h1>
                </div>

                <a
                    href="<?= BASE_URL; ?>/research/index.php"
                    class="btn btn-outline-asu"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Research
                </a>

            </div>

            <!-- =================================================
                 ERRORS
            ================================================== -->

            <?php if ($errors): ?>

                <div class="research-form-errors">

                    <strong>
                        Please review the following:
                    </strong>

                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li>
                                <?= e($error); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                </div>

            <?php endif; ?>

            <!-- =================================================
                 FORM
            ================================================== -->

            <section class="research-form-card">

                <div class="research-form-card-header">

                    <div class="research-form-header-icon">
                        <i class="bi bi-journal-plus"></i>
                    </div>

                    <div>
                        <h2>
                            Research Manuscript Information
                        </h2>
                    </div>

                </div>

                <div class="research-form-body">

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        autocomplete="off"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(generateCsrfToken()); ?>"
                        >

                        <!-- =====================================
                             RESEARCHER
                        ====================================== -->

                        <div class="research-form-section">

                            <div class="research-section-title">
                                <i class="bi bi-person"></i>
                                Researcher Information
                            </div>

                            <div class="row g-3 researcher-information-fields">

                                <div class="col-md-4">
                                    <label class="form-label" for="lead_researcher">
                                        Lead Researcher
                                    </label>
                                    <input
                                        type="text"
                                        id="lead_researcher"
                                        class="form-control"
                                        value="<?= e(fullName($user)); ?>"
                                        disabled
                                    >
                                </div>

                                <div class="col-md-4 research-assignment-item">
                                    <label for="college_id" class="form-label">
                                        College <span class="required-mark">*</span>
                                    </label>
                                    <select
                                        id="college_id"
                                        name="college_id"
                                        class="form-select"
                                        required
                                    >
                                        <option value="">Select College</option>
                                        <?php foreach ($activeColleges as $college): ?>
                                            <option
                                                value="<?= (int)$college['college_id']; ?>"
                                                <?= (int)$college['college_id'] === $selectedCollegeId ? 'selected' : ''; ?>
                                            ><?= e($college['college_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4 research-assignment-item">
                                    <label for="department_id" class="form-label">
                                        Department <span class="required-mark">*</span>
                                    </label>
                                    <select
                                        id="department_id"
                                        name="department_id"
                                        class="form-select"
                                        required
                                        <?= $selectedCollegeId <= 0 ? 'disabled' : ''; ?>
                                    >
                                        <option value="">Select Department</option>
                                        <?php foreach ($activeDepartments as $department): ?>
                                            <?php if ((int)$department['college_id'] !== $selectedCollegeId) continue; ?>
                                            <option
                                                value="<?= (int)$department['department_id']; ?>"
                                                <?= (int)$department['department_id'] === $selectedDepartmentId ? 'selected' : ''; ?>
                                            ><?= e($department['department_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>

                        </div>

                        <!-- =====================================
                             BASIC INFORMATION
                        ====================================== -->

                        <div class="research-form-section">

                            <div class="research-section-title">
                                <i class="bi bi-journal-text"></i>
                                Basic Research Information
                            </div>

                            <div class="row g-3">

                                <div class="col-12">
                                    <label
                                        for="research_title"
                                        class="form-label"
                                    >
                                        Research Title
                                        <span class="required-mark">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        id="research_title"
                                        name="research_title"
                                        class="form-control"
                                        maxlength="255"
                                        placeholder="Enter complete research title"
                                        value="<?= e($researchTitle); ?>"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label
                                        for="research_category"
                                        class="form-label"
                                    >
                                        Research Category
                                        <span class="required-mark">*</span>
                                    </label>

                                    <select
                                        id="research_category"
                                        name="research_category"
                                        class="form-select"
                                        required
                                    >
                                        <option value="" disabled <?= $researchCategory === '' ? 'selected' : ''; ?>>Select Research Category</option>
                                        <option value="Social Science" <?= $researchCategory === 'Social Science' ? 'selected' : ''; ?>>Social Science</option>
                                        <option value="Technical" <?= $researchCategory === 'Technical' ? 'selected' : ''; ?>>Technical</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label
                                        for="research_type"
                                        class="form-label"
                                    >
                                        Research Type
                                        <span class="required-mark">*</span>
                                    </label>

                                    <select
                                        id="research_type"
                                        name="research_type"
                                        class="form-select"
                                        required
                                    >
                                        <option value="">
                                            Select Research Type
                                        </option>

                                        <?php
                                        $researchTypes = [
                                            'Basic Research',
                                            'Applied Research',
                                            'Developmental Research',
                                            'Action Research',
                                            'Other'
                                        ];
                                        ?>

                                        <?php foreach ($researchTypes as $typeOption): ?>
                                            <option
                                                value="<?= e($typeOption); ?>"
                                                <?= $researchType === $typeOption
                                                    ? 'selected'
                                                    : ''; ?>
                                            >
                                                <?= e($typeOption); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>

                        </div>

                        <!-- =====================================
                             CONTENT
                        ====================================== -->

                        <div class="research-form-section">

                            <div class="research-section-title">
                                <i class="bi bi-file-text"></i>
                                Research Content
                            </div>

                            <div class="row g-3">

                                <div class="col-12">
                                    <label
                                        for="abstract"
                                        class="form-label"
                                    >
                                        Abstract
                                        <span class="required-mark">*</span>
                                    </label>

                                    <textarea
                                        id="abstract"
                                        name="abstract"
                                        class="form-control"
                                        placeholder="Enter the research abstract"
                                        required
                                    ><?= e($abstract); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label
                                        for="objectives"
                                        class="form-label"
                                    >
                                        Objectives
                                        <span class="required-mark">*</span>
                                    </label>

                                    <textarea
                                        id="objectives"
                                        name="objectives"
                                        class="form-control"
                                        placeholder="Enter the general and specific objectives"
                                        required
                                    ><?= e($objectives); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label
                                        for="methodology"
                                        class="form-label"
                                    >
                                        Methodology
                                        <span class="required-mark">*</span>
                                    </label>

                                    <textarea
                                        id="methodology"
                                        name="methodology"
                                        class="form-control"
                                        placeholder="Describe the research methodology"
                                        required
                                    ><?= e($methodology); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label
                                        for="expected_output"
                                        class="form-label"
                                    >
                                        Expected Output
                                        <span class="required-mark">*</span>
                                    </label>

                                    <textarea
                                        id="expected_output"
                                        name="expected_output"
                                        class="form-control"
                                        placeholder="Describe the expected research output"
                                        required
                                    ><?= e($expectedOutput); ?></textarea>
                                </div>

                            </div>

                        </div>

                        <!-- =====================================
                             FUNDING / SCHEDULE
                        ====================================== -->

                        <div class="research-form-section">

                            <div class="research-section-title">
                                <i class="bi bi-wallet2"></i>
                                Funding & Schedule
                            </div>

                            <!-- Two equal, precisely aligned columns.
                                 Row 1: Funding Source | Proposed Start Date
                                 Row 2: Estimated Budget | Target Completion Date -->
                            <div class="asu-funding-schedule-grid">

                                <div class="asu-fs-field asu-fs-funding">
                                    <label for="funding_source" class="form-label">
                                        Funding Source <span class="required-mark">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="funding_source"
                                        name="funding_source"
                                        class="form-control"
                                        maxlength="150"
                                        placeholder="Example: Self-funded, ASU, CHED, DOST"
                                        value="<?= e($fundingSource); ?>"
                                        required
                                    >
                                    <small class="research-field-note">
                                        This identifies where the research fund will come from.
                                    </small>
                                </div>

                                <div class="asu-fs-field asu-fs-start">
                                    <label for="start_date" class="form-label">
                                        Proposed Start Date
                                    </label>
                                    <input
                                        type="date"
                                        id="start_date"
                                        name="start_date"
                                        class="form-control"
                                        value="<?= e($startDate); ?>"
                                    >
                                </div>

                                <div class="asu-fs-field asu-fs-budget">
                                    <label for="estimated_budget" class="form-label">
                                        Estimated Budget
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input
                                            type="number"
                                            id="estimated_budget"
                                            name="estimated_budget"
                                            class="form-control"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                            value="<?= e($estimatedBudget); ?>"
                                        >
                                    </div>
                                </div>

                                <div class="asu-fs-field asu-fs-target">
                                    <label for="target_completion_date" class="form-label">
                                        Target Completion Date
                                    </label>
                                    <input
                                        type="date"
                                        id="target_completion_date"
                                        name="target_completion_date"
                                        class="form-control"
                                        value="<?= e($targetCompletionDate); ?>"
                                    >
                                </div>

                            </div>

                        </div>

                        <!-- =====================================
                             DOCUMENT UPLOADS
                        ====================================== -->

                        <div class="research-form-section">

                            <div class="research-section-title">
                                <i class="bi bi-cloud-arrow-up"></i>
                                Required Uploads
                            </div>

                            <div class="research-upload-grid upload-layout-reordered">

                                <div class="research-upload-box manuscript-upload-box upload-link-box">
                                    <div class="upload-link-info">
                                        <div class="research-upload-icon">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </div>
                                        <div class="manuscript-upload-text">
                                            <h5>Manuscript Link <span class="required-mark">*</span></h5>
                                            
                                        </div>
                                    </div>
                                    <div class="upload-link-body">
                                        <div class="upload-link-controls">
                                            <input
                                                type="url"
                                                id="manuscript_link"
                                                name="manuscript_link"
                                                class="form-control"
                                                placeholder="https://drive.google.com/..."
                                                maxlength="255"
                                                value="<?= e($manuscriptLink); ?>"
                                                aria-label="Manuscript Link"
                                                required
                                            >
                                            <a
                                                id="viewManuscriptPreview"
                                                href="#"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-outline-asu upload-link-view is-disabled upload-link-icon-btn"
                                                aria-disabled="true"
                                                aria-label="Preview manuscript link"
                                                title="Preview manuscript link"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                        <small class="research-field-note rems-upload-info-note" id="manuscriptViewMessage" role="status" aria-live="polite">
                                            Paste a shareable HTTPS manuscript link. You must click the eye button to view it before saving.
                                        </small>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- =====================================
                             ACTIONS
                        ====================================== -->

                        <div class="research-form-actions">

                            <div class="research-draft-note">
                                <i class="bi bi-info-circle"></i>

                                <span>
                                    After saving, the system will assign a manuscript number.
                                    You can submit the record for RPS processing from the details page.
                                </span>
                            </div>

                            <div class="research-action-buttons">

                                <a
                                    href="<?= BASE_URL; ?>/research/index.php"
                                    class="btn btn-outline-asu"
                                >
                                    Cancel
                                </a>

                                <button
                                    type="submit"
                                    id="saveManuscriptButton"
                                    class="btn btn-asu"
                                    disabled
                                >
                                    <i class="bi bi-save me-1"></i>
                                    Save Manuscript
                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </section>
</div>

    </main>

</div>

<script>
// Update departments without a page reload, using the Active Superadmin master data.
document.addEventListener('DOMContentLoaded', function () {
    const collegeSelect = document.getElementById('college_id');
    const departmentSelect = document.getElementById('department_id');
    if (!collegeSelect || !departmentSelect) return;

    const departments = <?= json_encode(
        $activeDepartments,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>;

    collegeSelect.addEventListener('change', function () {
        const collegeId = Number(collegeSelect.value);
        departmentSelect.replaceChildren(new Option('Select Department', ''));
        departmentSelect.disabled = !collegeId;

        if (!collegeId) return;
        departments.forEach(function (department) {
            if (Number(department.college_id) === collegeId) {
                departmentSelect.add(new Option(
                    department.department_name,
                    String(department.department_id)
                ));
            }
        });
        // A new college must always require a new department selection.
        departmentSelect.value = '';
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('manuscript_link');
    const visit = document.getElementById('viewManuscriptPreview');
    const saveButton = document.getElementById('saveManuscriptButton');
    const viewMessage = document.getElementById('manuscriptViewMessage');
    const form = input ? input.closest('form') : null;

    if (!input || !visit || !saveButton) return;

    let visited = false;
    let validUrl = '';

    function validateHttpsUrl(value) {
        const text = value.trim();
        if (!text || text.length > 255) return '';
        try {
            const url = new URL(text);
            return (url.protocol === 'https:' && url.hostname && !url.username && !url.password)
                ? url.href
                : '';
        } catch (error) {
            return '';
        }
    }

    function updateControls() {
        saveButton.disabled = !validUrl || !visited;
        if (viewMessage) {
            viewMessage.classList.toggle('is-link-opened', !!validUrl && visited);
            viewMessage.textContent = visited && validUrl
                ? 'Link opened successfully. You may now save the manuscript.'
                : 'Paste a shareable HTTPS manuscript link. You must click the eye button to view it before saving.';
        }
    }

    function refreshLink() {
        validUrl = validateHttpsUrl(input.value);
        visited = false;
        visit.removeAttribute('href');
        visit.classList.add('is-disabled');
        visit.setAttribute('aria-disabled', 'true');

        if (validUrl) {
            visit.href = validUrl;
            visit.classList.remove('is-disabled');
            visit.setAttribute('aria-disabled', 'false');
        }

        updateControls();
    }

    visit.addEventListener('click', function (event) {
        if (!validUrl || visit.classList.contains('is-disabled')) {
            event.preventDefault();
            return;
        }
        visited = true;
        updateControls();
    });

    input.addEventListener('input', refreshLink);

    if (form) {
        form.addEventListener('submit', function (event) {
            if (!visited || !validUrl || validateHttpsUrl(input.value) !== validUrl) {
                event.preventDefault();
            }
        });
    }

    refreshLink();
});
</script>

<?php

require __DIR__ . '/../includes/footer.php';

?>
