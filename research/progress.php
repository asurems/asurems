<?php

require_once __DIR__ . '/../includes/auth.php';

$notificationsFile = __DIR__ . '/../includes/notifications.php';

if (is_file($notificationsFile)) {
    require_once $notificationsFile;
}

requireLogin();


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $_SESSION['role'] ?? '',
        [
            'admin',
            'faculty',
            'rps'
        ],
        true
    )
) {
    redirect(
        BASE_URL . '/dashboard.php'
    );
}


$user = currentUser();

$pageTitle = 'Research Progress';
$activePage = 'project_monitoring';

$errors = [];

$displayRole =
    ($user['role'] ?? '') === 'rps'
        ? 'Research Processing Staff (RPS)'
        : roleLabel(
            $user['role'] ?? ''
        );


/*
|--------------------------------------------------------------------------
| RESEARCH ID
|--------------------------------------------------------------------------
*/

$researchId =
    isset($_GET['id'])
        ? (int)$_GET['id']
        : 0;


if ($researchId <= 0) {

    setFlash(
        'danger',
        'Invalid research record.'
    );

    redirect(
        BASE_URL . '/research/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD RESEARCH
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        rp.research_id,
        rp.researcher_id,
        rp.research_title,
        rp.status,
        rp.progress_percentage,
        rp.start_date,
        rp.target_completion_date,
        rp.manuscript_no,
        rp.submission_track,
        rp.workflow_status,
        rp.workflow_stage,
        rp.workflow_updated_at,
        rp.submitted_at,
        rp.approved_at,
        rp.completed_at,

        u.first_name,
        u.middle_name,
        u.last_name,

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
    $researchId
]);


$research =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$research) {

    setFlash(
        'danger',
        'Research proposal not found.'
    );

    redirect(
        BASE_URL . '/research/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| FACULTY OWNERSHIP
|--------------------------------------------------------------------------
*/

if (
    $user['role'] === 'faculty'
    &&
    (int)$research['researcher_id']
    !==
    (int)$user['user_id']
) {

    setFlash(
        'danger',
        'You are not authorized to access this research project.'
    );

    redirect(
        BASE_URL . '/research/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| VALID PROJECT MONITORING STATUS
|--------------------------------------------------------------------------
|
| Project Monitoring opens only after Notice to Proceed is issued.
| Faculty may update only their own active implementation projects.
| RPS/Admin may view monitored projects, but they cannot add updates.
|
*/

$workflowStatus =
    strtoupper(
        trim(
            (string)(
                $research['workflow_status']
                ?? ''
            )
        )
    );

$workflowStage =
    strtoupper(
        trim(
            (string)(
                $research['workflow_stage']
                ?? ''
            )
        )
    );


$isSelfInitiated =
    strtoupper(
        trim(
            (string)(
                $research['submission_track']
                ?? ''
            )
        )
    )
    === 'SELF_INITIATED';

$isSelfInitiatedFinalRecord =
    $isSelfInitiated
    &&
    (
        $workflowStatus
        === 'SELF_INITIATED_COMPLETED'
        ||
        $research['status']
        === 'Completed'
    );


$hasNoticeToProceed =
    in_array(
        $workflowStatus,
        [
            'NTP_ISSUED',
            'COMPLETED_MANUSCRIPT_SUBMITTED',
            'FOR_TERMINAL_REPORT',
            'TERMINAL_REPORT_SUBMITTED',
            'PERMIT_ISSUED',
            'COMPLETED'
        ],
        true
    )
    ||
    in_array(
        $workflowStage,
        [
            'IMPLEMENTATION',
            'COMPLETION',
            'TERMINAL_REPORT',
            'PUBLICATION_PRESENTATION',
            'COMPLETED'
        ],
        true
    )
    ||
    in_array(
        $research['status'],
        [
            'Ongoing',
            'Completed'
        ],
        true
    );

$canMonitor = $hasNoticeToProceed;

$isAssignedFaculty =
    $user['role'] === 'faculty'
    &&
    (int)$research['researcher_id']
    ===
    (int)$user['user_id'];

if (!$canMonitor) {

    setFlash(
        'warning',
        'Project Monitoring is available only after the Notice to Proceed has been issued.'
    );

    redirect(
        BASE_URL .
        '/research/view.php?id=' .
        $researchId
    );
}


/*
|--------------------------------------------------------------------------
| DOCUMENT CHECK: COMPLETED RESEARCH MANUSCRIPT
|--------------------------------------------------------------------------
*/

$completedManuscriptDocument = null;
$hasCompletedManuscriptDocument = false;

$completedDocumentStmt =
    $pdo->prepare("
        SELECT
            document_id,
            document_type,
            original_name,
            stored_name,
            file_path,
            file_extension,
            file_size,
            uploaded_at

        FROM research_documents

        WHERE research_id = ?
          AND LOWER(TRIM(document_type)) IN
          (
              'completed manuscript',
              'completed research manuscript',
              'final manuscript',
              'manuscript of completed research',
              'self-initiated completed research',
              'self initiated completed research'
          )

        ORDER BY
            uploaded_at DESC,
            document_id DESC

        LIMIT 1
    ");

$completedDocumentStmt->execute([
    $researchId
]);

$completedManuscriptDocument =
    $completedDocumentStmt->fetch(
        PDO::FETCH_ASSOC
    )
    ?: null;

$hasCompletedManuscriptDocument =
    $completedManuscriptDocument !== null;

$canUploadCompletedManuscript =
    $isAssignedFaculty
    &&
    $hasNoticeToProceed
    &&
    $research['status'] === 'Ongoing'
    &&
    !$hasCompletedManuscriptDocument
    &&
    !in_array(
        $workflowStatus,
        [
            'COMPLETED_MANUSCRIPT_SUBMITTED',
            'FOR_TERMINAL_REPORT',
            'TERMINAL_REPORT_SUBMITTED',
            'PERMIT_ISSUED',
            'COMPLETED'
        ],
        true
    );


/*
|--------------------------------------------------------------------------
| DOCUMENT CHECK: TERMINAL REPORT
|--------------------------------------------------------------------------
*/

$terminalReportDocument = null;
$hasTerminalReportDocument = false;

$terminalReportStmt =
    $pdo->prepare("
        SELECT
            document_id,
            document_type,
            original_name,
            stored_name,
            file_path,
            file_extension,
            file_size,
            uploaded_at

        FROM research_documents

        WHERE research_id = ?
          AND LOWER(TRIM(document_type)) IN
          (
              'terminal report',
              'research terminal report',
              'final terminal report',
              'self-initiated terminal report',
              'self initiated terminal report'
          )

        ORDER BY
            uploaded_at DESC,
            document_id DESC

        LIMIT 1
    " );

$terminalReportStmt->execute([
    $researchId
]);

$terminalReportDocument =
    $terminalReportStmt->fetch(
        PDO::FETCH_ASSOC
    )
    ?: null;

$hasTerminalReportDocument =
    $terminalReportDocument !== null;

$canUploadTerminalReport =
    $isAssignedFaculty
    &&
    $research['status'] === 'Ongoing'
    &&
    $workflowStatus === 'FOR_TERMINAL_REPORT'
    &&
    !$hasTerminalReportDocument;



/*
|--------------------------------------------------------------------------
| REGULAR FOR-URRC FINAL STEP:
| PROOF OF PUBLICATION / PRESENTATION
|--------------------------------------------------------------------------
|
| This belongs ONLY to the regular review path.
| Flow:
| Terminal Report -> RPS issues Permit -> Faculty uploads proof -> Completed
|
*/

$publicationPresentationProofDocument = null;
$hasPublicationPresentationProofDocument = false;

if (!$isSelfInitiated) {

    $proofDocumentStmt =
        $pdo->prepare("
            SELECT
                document_id,
                document_type,
                original_name,
                stored_name,
                file_path,
                file_extension,
                file_size,
                uploaded_at

            FROM research_documents

            WHERE research_id = ?
              AND LOWER(TRIM(document_type)) IN
              (
                  'proof of publication',
                  'proof of presentation',
                  'proof of publication/presentation',
                  'publication proof',
                  'presentation proof'
              )

            ORDER BY
                uploaded_at DESC,
                document_id DESC

            LIMIT 1
        ");

    $proofDocumentStmt->execute([
        $researchId
    ]);

    $publicationPresentationProofDocument =
        $proofDocumentStmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: null;

    $hasPublicationPresentationProofDocument =
        $publicationPresentationProofDocument !== null;
}


/*
|--------------------------------------------------------------------------
| VERIFY RPS PERMIT ISSUANCE
|--------------------------------------------------------------------------
*/

$latestPermitAudit = null;
$hasPermitIssuanceAudit = false;
$permitProofTypeLabel = 'Publication / Presentation';

if (!$isSelfInitiated) {

    $permitAuditStmt =
        $pdo->prepare("
            SELECT
                rsh.remarks,
                rsh.changed_at,
                u.role

            FROM research_status_history rsh

            LEFT JOIN users u
                ON u.user_id = rsh.changed_by

            WHERE rsh.research_id = ?
              AND (
                    u.user_id IS NULL
                    OR LOWER(TRIM(u.role)) = 'rps'
                  )
              AND LOWER(
                    COALESCE(
                        rsh.remarks,
                        ''
                    )
                  ) LIKE '%permit for % issued%'

            ORDER BY
                rsh.changed_at DESC

            LIMIT 1
        ");

    $permitAuditStmt->execute([
        $researchId
    ]);

    $latestPermitAudit =
        $permitAuditStmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: null;

    $hasPermitIssuanceAudit =
        $latestPermitAudit !== null;

    if ($latestPermitAudit) {

        $permitAuditRemarks =
            strtoupper(
                (string)(
                    $latestPermitAudit['remarks']
                    ?? ''
                )
            );

        if (
            str_contains(
                $permitAuditRemarks,
                '[PERMIT_TYPE:PUBLICATION]'
            )
        ) {
            $permitProofTypeLabel =
                'Publication';

        } elseif (
            str_contains(
                $permitAuditRemarks,
                '[PERMIT_TYPE:PRESENTATION]'
            )
        ) {
            $permitProofTypeLabel =
                'Presentation';
        }
    }
}


$canUploadPublicationPresentationProof =
    !$isSelfInitiated
    &&
    $isAssignedFaculty
    &&
    $research['status'] === 'Ongoing'
    &&
    $workflowStatus === 'PERMIT_ISSUED'
    &&
    $hasTerminalReportDocument
    &&
    $hasPermitIssuanceAudit
    &&
    !$hasPublicationPresentationProofDocument;



/*
|--------------------------------------------------------------------------
| SELF-INITIATED FINAL RECORD SUPPORT
|--------------------------------------------------------------------------
|
| ADDITIVE ONLY:
| The regular Project Monitoring records remain unchanged.
| These variables provide the extra records needed by the
| Self-Initiated Research Workflow summary.
|
*/

$upeCertificate1Document = null;
$upeCertificate2Document = null;
$selfInitiatedPublicationDocument = null;

if ($isSelfInitiated) {

    $selfInitiatedDocumentsStmt =
        $pdo->prepare("
            SELECT
                document_id,
                document_type,
                original_name,
                stored_name,
                file_path,
                file_extension,
                file_size,
                uploaded_at

            FROM research_documents

            WHERE research_id = ?

            ORDER BY
                uploaded_at DESC,
                document_id DESC
        ");

    $selfInitiatedDocumentsStmt->execute([
        $researchId
    ]);

    $selfInitiatedDocuments =
        $selfInitiatedDocumentsStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    foreach (
        $selfInitiatedDocuments
        as
        $selfDocument
    ) {

        $selfDocumentType =
            strtolower(
                trim(
                    (string)(
                        $selfDocument['document_type']
                        ?? ''
                    )
                )
            );

        if (
            $upeCertificate1Document === null
            &&
            $selfDocumentType === 'upe certificate 1'
        ) {
            $upeCertificate1Document =
                $selfDocument;
        }

        if (
            $upeCertificate2Document === null
            &&
            $selfDocumentType === 'upe certificate 2'
        ) {
            $upeCertificate2Document =
                $selfDocument;
        }

        if (
            $selfInitiatedPublicationDocument === null
            &&
            in_array(
                $selfDocumentType,
                [
                    'self-initiated publication',
                    'self initiated publication',
                    'self-initiated proof of publication/presentation',
                    'self initiated proof of publication/presentation',
                    'self-initiated proof of publication / presentation',
                    'self initiated proof of publication / presentation',
                    'proof of publication',
                    'proof of presentation',
                    'publication',
                    'publication file'
                ],
                true
            )
        ) {
            $selfInitiatedPublicationDocument =
                $selfDocument;
        }
    }
}


$selfInitiatedPermitIssuedAt =
    !empty($terminalReportDocument['uploaded_at'])
        ? (string)$terminalReportDocument['uploaded_at']
        : (
            !empty($research['workflow_updated_at'])
                ? (string)$research['workflow_updated_at']
                : (
                    !empty($research['completed_at'])
                        ? (string)$research['completed_at']
                        : date('Y-m-d H:i:s')
                )
        );


$selfInitiatedPermitNumber =
    'SI-PERMIT-'
    . date(
        'Y',
        strtotime(
            $selfInitiatedPermitIssuedAt
        )
    )
    . '-'
    . str_pad(
        (string)$researchId,
        5,
        '0',
        STR_PAD_LEFT
    );


$selfInitiatedPermitUrl =
    BASE_URL
    . '/research/view.php?id='
    . $researchId
    . '&certificate=self_initiated_permit';


/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORIES
|--------------------------------------------------------------------------
*/

$uploadDirectory =
    __DIR__ .
    '/../uploads/research/progress/';

$completedManuscriptUploadDirectory =
    __DIR__ .
    '/../uploads/research/completed_manuscripts/';

$terminalReportUploadDirectory =
    __DIR__ .
    '/../uploads/research/terminal_reports/';

$publicationPresentationProofUploadDirectory =
    __DIR__ .
    '/../uploads/research/publication_presentation_proofs/';

foreach (
    [
        $uploadDirectory,
        $completedManuscriptUploadDirectory,
        $terminalReportUploadDirectory,
        $publicationPresentationProofUploadDirectory
    ]
    as
    $directory
) {

    if (!is_dir($directory)) {

        mkdir(
            $directory,
            0755,
            true
        );
    }
}


/*
|--------------------------------------------------------------------------
| HELPERS FOR COMPLETED MANUSCRIPT UPLOAD
|--------------------------------------------------------------------------
*/

function progressEnsureResearchDocumentTypeFlexible(
    PDO $pdo
): void {

    try {

        $pdo->exec("
            ALTER TABLE research_documents
            MODIFY document_type VARCHAR(100) NOT NULL
        ");

    } catch (Throwable $e) {

        error_log(
            'Research document type flexibility check failed: '
            . $e->getMessage()
        );
    }
}


/* HTTPS-only external research document link validation (same as research/view.php). */
function progressExternalDocumentLink(string $input): ?string
{
    $url = trim($input);
    if ($url === '' || strlen($url) > 255 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    $host = strtolower((string)$parts['host']);
    if ($host === 'localhost' || str_ends_with($host, '.localhost')
        || str_ends_with($host, '.local') || filter_var($host, FILTER_VALIDATE_IP)) {
        return null;
    }
    return $url;
}

/* Keep the existing POST handlers and workflow transactions; change their input to links. */
function progressValidateManuscriptUpload(
    string $fieldName,
    array &$errors,
    string $friendlyName = 'Completed research manuscript'
): ?array {
    $linkField = preg_replace('/_file$/', '_link', $fieldName);
    $url = progressExternalDocumentLink((string)($_POST[$linkField] ?? ''));
    if ($url === null) {
        $errors[] = 'Enter a valid HTTPS link for ' . strtolower($friendlyName)
            . ' (maximum 255 characters).';
        return null;
    }
    return [
        'original_name' => $friendlyName . ' Link',
        'extension' => 'url',
        'size' => 0,
        'url' => $url
    ];
}

function progressMoveCompletedManuscriptUpload(
    array $file,
    string $uploadDirectory,
    int $researchId
): array {
    // No physical file is uploaded or moved; document_path stores the HTTPS URL.
    return [
        'stored_name' => 'linked_completed_manuscript_' . $researchId . '_'
            . bin2hex(random_bytes(5)),
        'physical_path' => null,
        'database_path' => $file['url']
    ];
}

function progressMoveTerminalReportUpload(
    array $file,
    string $uploadDirectory,
    int $researchId
): array {
    return [
        'stored_name' => 'linked_terminal_report_' . $researchId . '_'
            . bin2hex(random_bytes(5)),
        'physical_path' => null,
        'database_path' => $file['url']
    ];
}

/*
    |--------------------------------------------------------------------------
    | PROOF OF PUBLICATION / PRESENTATION HELPERS
|--------------------------------------------------------------------------
*/

function progressValidatePublicationPresentationProofUpload(
    string $fieldName,
    array &$errors
): ?array {
    $linkField = preg_replace('/_file$/', '_link', $fieldName);
    $url = progressExternalDocumentLink((string)($_POST[$linkField] ?? ''));
    if ($url === null) {
        $errors[] = 'Enter a valid HTTPS proof of publication/presentation link (maximum 255 characters).';
        return null;
    }
    return [
        'original_name' => 'Proof of Publication/Presentation Link',
        'extension' => 'url',
        'size' => 0,
        'url' => $url
    ];
}

function progressMovePublicationPresentationProofUpload(
    array $file,
    string $uploadDirectory,
    int $researchId
): array {
    return [
        'stored_name' => 'linked_publication_presentation_proof_' . $researchId . '_'
            . bin2hex(random_bytes(5)),
        'physical_path' => null,
        'database_path' => $file['url']
    ];
}

/*
    |--------------------------------------------------------------------------
    | COMPLETED MANUSCRIPT: RECORD LINK VIEW BEFORE SUBMISSION
    |--------------------------------------------------------------------------
    | The eye button opens the submitted HTTPS link in a new tab. A separate,
    | CSRF-checked POST records that view for this logged-in researcher and
    | research record. A different/edited URL needs to be viewed again.
    | This action does not change the database or workflow status.
    */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'confirm_completed_manuscript_link_view'
) {
    header('Content-Type: application/json; charset=utf-8');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid request. Refresh the page and try again.']);
        exit;
    }

    if (!$canUploadCompletedManuscript || !$isAssignedFaculty) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Manuscript submission is not available.']);
        exit;
    }

    $viewedManuscriptUrl = progressExternalDocumentLink(
        (string)($_POST['completed_manuscript_link'] ?? '')
    );

    if ($viewedManuscriptUrl === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Enter a valid public HTTPS link first.']);
        exit;
    }

    $manuscriptViewSessionKey = $researchId . ':' . (int)$user['user_id'];
    $_SESSION['progress_completed_manuscript_views'][$manuscriptViewSessionKey] =
        hash('sha256', $viewedManuscriptUrl);

    echo json_encode(['success' => true]);
    exit;
}


/*
    |--------------------------------------------------------------------------
    | OTHER PROJECT DOCUMENTS: RECORD EYE-BUTTON LINK VISITS
    |--------------------------------------------------------------------------
    | This lightweight, CSRF-protected session check mirrors the completed
    | manuscript link requirement. It never submits the document itself.
    */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'confirm_progress_document_link_view'
) {
    header('Content-Type: application/json; charset=utf-8');

    $viewKind = (string)($_POST['view_kind'] ?? '');
    $viewForms = [
        'terminal_report' => [
            'field' => 'terminal_report_link',
            'allowed' => $canUploadTerminalReport
        ],
        'publication_presentation_proof' => [
            'field' => 'publication_presentation_proof_link',
            'allowed' => $canUploadPublicationPresentationProof
        ]
    ];

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid request. Refresh the page and try again.']);
        exit;
    }

    if (!isset($viewForms[$viewKind]) || !$viewForms[$viewKind]['allowed'] || !$isAssignedFaculty) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Document link submission is not available.']);
        exit;
    }

    $viewedUrl = progressExternalDocumentLink(
        (string)($_POST[$viewForms[$viewKind]['field']] ?? '')
    );
    if ($viewedUrl === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Enter a valid HTTPS document link first.']);
        exit;
    }

    $viewSessionKey = $researchId . ':' . (int)$user['user_id'] . ':' . $viewKind;
    $_SESSION['progress_other_document_views'][$viewSessionKey] = hash('sha256', $viewedUrl);
    echo json_encode(['success' => true]);
    exit;
}


/*
    |--------------------------------------------------------------------------
    | POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    $action =
        $_POST['action']
        ?? '';

    if (
        !in_array(
            $action,
            [
                'upload_completed_manuscript',
                'upload_terminal_report',
                'upload_publication_presentation_proof'
            ],
            true
        )
    ) {

        $errors[] =
            'Invalid request action.';
    }

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
    | UPLOAD COMPLETED RESEARCH MANUSCRIPT
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'upload_completed_manuscript'
        &&
        !$errors
    ) {

        /*
        |--------------------------------------------------------------------------
        | DUPLICATE-SUBMIT SAFETY
        |--------------------------------------------------------------------------
        |
        | If a fast double-click or browser re-submit sends this request twice,
        | and the first request already completed successfully, do not show a
        | misleading red error on the second request.
        |
        */
        if (
            !$canUploadCompletedManuscript
            &&
            $isAssignedFaculty
            &&
            $hasCompletedManuscriptDocument
            &&
            $workflowStatus === 'COMPLETED_MANUSCRIPT_SUBMITTED'
        ) {

            setFlash(
                'success',
                'Completed research manuscript is already submitted successfully.'
            );

            redirect(
                BASE_URL .
                '/research/progress.php?id=' .
                $researchId
            );
        }

        if (!$canUploadCompletedManuscript) {

            $errors[] =
                'Completed manuscript submission is not available for this research record at its current workflow step.';
        }

        $completedFile = null;

        if (!$errors) {

            $completedFile =
                progressValidateManuscriptUpload(
                    'completed_manuscript_file',
                    $errors,
                    'Completed research manuscript'
                );
        }

        // The submitted link must be the same URL opened with the eye button.
        // This check also blocks direct POST requests that bypass disabled UI.
        if (!$errors && $completedFile) {
            $manuscriptViewSessionKey = $researchId . ':' . (int)$user['user_id'];
            $recordedViewHash = $_SESSION['progress_completed_manuscript_views'][$manuscriptViewSessionKey]
                ?? null;

            if (!is_string($recordedViewHash)
                || !hash_equals($recordedViewHash, hash('sha256', $completedFile['url']))) {
                $errors[] = 'Please click the eye button to view the completed manuscript link before submitting.';
            }
        }

        if (!$errors && $completedFile) {

            $movedCompletedPath = null;

            try {

                // IMPORTANT: ALTER TABLE may cause an implicit COMMIT in MySQL.
                // Run the schema compatibility check before starting the upload transaction
                // so a successful manuscript upload does not end with a false error.
                progressEnsureResearchDocumentTypeFlexible($pdo);

                $pdo->beginTransaction();

                $completedUpload =
                    progressMoveCompletedManuscriptUpload(
                        $completedFile,
                        $completedManuscriptUploadDirectory,
                        $researchId
                    );

                $movedCompletedPath =
                    $completedUpload['physical_path'];

                $documentInsert =
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
                            'Completed Manuscript',
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ");

                $documentInsert->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $completedFile['original_name'],
                    $completedUpload['stored_name'],
                    $completedUpload['database_path'],
                    $completedFile['extension'],
                    $completedFile['size']
                ]);

                $updateResearch =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            progress_percentage = 100,
                            workflow_status = 'COMPLETED_MANUSCRIPT_SUBMITTED',
                            workflow_stage = 'COMPLETION',
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND status = 'Ongoing'
                          AND workflow_status NOT IN
                          (
                              'COMPLETED_MANUSCRIPT_SUBMITTED',
                              'TERMINAL_REPORT_SUBMITTED',
                              'PERMIT_ISSUED',
                              'COMPLETED'
                          )
                    ");

                $updateResearch->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);

                if ($updateResearch->rowCount() !== 1) {

                    throw new RuntimeException(
                        'Unable to update completed manuscript workflow status.'
                    );
                }

                $history =
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
                            ?,
                            ?,
                            ?
                        )
                    ");

                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    (string)$research['status'],
                    'Completed research manuscript link submitted to the Research Office for next processing.'
                ]);

                /*
                |--------------------------------------------------------------
                | COMMIT THE CORE SUBMISSION FIRST
                |--------------------------------------------------------------
                | Notifications are intentionally sent only AFTER the upload,
                | document record, workflow update, and history are committed.
                | A notification failure must never delete a valid manuscript.
                */
                $pdo->commit();

                unset($_SESSION['progress_completed_manuscript_views'][
                    $researchId . ':' . (int)$user['user_id']
                ]);

                if (function_exists('notifyUsersByRole')) {
                    try {
                        notifyUsersByRole(
                            $pdo,
                            [
                                'rps',
                                'admin'
                            ],
                            'Completed research manuscript submitted',
                            fullName($user)
                            . ' submitted the completed research manuscript for '
                            . $research['research_title']
                            . '.',
                            'research',
                            $researchId,
                            BASE_URL . '/research/view.php?id=' . $researchId,
                            (int)$user['user_id']
                        );
                    } catch (Throwable $notificationError) {
                        error_log(
                            'Completed Manuscript Notification Error: '
                            . $notificationError->getMessage()
                        );
                    }
                }

                setFlash(
                    'success',
                    'Completed research manuscript submitted successfully.'
                );

                redirect(
                    BASE_URL .
                    '/research/progress.php?id=' .
                    $researchId
                );

            } catch (Throwable $e) {

                $rolledBack = false;

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                    $rolledBack = true;
                }

                /*
                | Delete the moved file only when the database transaction was
                | actually rolled back. If the transaction was already committed,
                | preserve the file so the saved document record remains valid.
                */
                if (
                    $rolledBack
                    &&
                    $movedCompletedPath
                    &&
                    is_file($movedCompletedPath)
                ) {
                    unlink($movedCompletedPath);
                }

                error_log(
                    'Completed Research Manuscript Upload Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to submit the completed research manuscript. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD TERMINAL REPORT
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'upload_terminal_report'
        &&
        !$errors
    ) {

        /*
        | Duplicate-submit safety: if the first request already succeeded,
        | treat a repeated request as success instead of showing a red error.
        */
        if (
            !$canUploadTerminalReport
            &&
            $isAssignedFaculty
            &&
            $hasTerminalReportDocument
            &&
            $workflowStatus === 'TERMINAL_REPORT_SUBMITTED'
        ) {

            setFlash(
                'success',
                'Terminal report is already submitted successfully.'
            );

            redirect(
                BASE_URL .
                '/research/progress.php?id=' .
                $researchId
            );
        }

        if (!$canUploadTerminalReport) {

            $errors[] =
                'Terminal report submission is not available for this research record at its current workflow step.';
        }

        $terminalFile = null;

        if (!$errors) {

            $terminalFile =
                progressValidateManuscriptUpload(
                    'terminal_report_file',
                    $errors,
                    'Terminal report'
                );
        }

        // A direct POST must not bypass the Terminal Report eye-button check.
        if (!$errors && $terminalFile) {
            $viewSessionKey = $researchId . ':' . (int)$user['user_id'] . ':terminal_report';
            $lastViewedHash = $_SESSION['progress_other_document_views'][$viewSessionKey] ?? null;
            if (!is_string($lastViewedHash)
                || !hash_equals($lastViewedHash, hash('sha256', $terminalFile['url']))) {
                $errors[] = 'Please click the eye button to view the Terminal Report link before submitting.';
            }
        }

        if (!$errors && $terminalFile) {

            $movedTerminalPath = null;

            try {

                // Keep schema-changing SQL outside the transaction.
                progressEnsureResearchDocumentTypeFlexible($pdo);

                $pdo->beginTransaction();

                $terminalUpload =
                    progressMoveTerminalReportUpload(
                        $terminalFile,
                        $terminalReportUploadDirectory,
                        $researchId
                    );

                $movedTerminalPath =
                    $terminalUpload['physical_path'];

                $terminalInsert =
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
                            'Terminal Report',
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    " );

                $terminalInsert->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $terminalFile['original_name'],
                    $terminalUpload['stored_name'],
                    $terminalUpload['database_path'],
                    $terminalFile['extension'],
                    $terminalFile['size']
                ]);

                $terminalUpdate =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            progress_percentage = 100,
                            workflow_status = 'TERMINAL_REPORT_SUBMITTED',
                            workflow_stage = 'PUBLICATION_PRESENTATION',
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND status = 'Ongoing'
                          AND workflow_status = 'FOR_TERMINAL_REPORT'
                    " );

                $terminalUpdate->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);

                if ($terminalUpdate->rowCount() !== 1) {

                    throw new RuntimeException(
                        'Unable to update terminal report workflow status.'
                    );
                }

                $terminalHistory =
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
                            ?,
                            ?,
                            ?
                        )
                    " );

                $terminalHistory->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    (string)$research['status'],
                    'Terminal report link submitted to the Research Office for permit processing.'
                ]);

                // Commit the core record before sending notifications.
                $pdo->commit();
                unset($_SESSION['progress_other_document_views'][
                    $researchId . ':' . (int)$user['user_id'] . ':terminal_report'
                ]);

                if (function_exists('notifyUsersByRole')) {
                    try {
                        notifyUsersByRole(
                            $pdo,
                            [
                                'rps',
                                'admin'
                            ],
                            'Terminal report submitted',
                            fullName($user)
                            . ' submitted the terminal report for '
                            . $research['research_title']
                            . '.',
                            'research',
                            $researchId,
                            BASE_URL . '/research/view.php?id=' . $researchId,
                            (int)$user['user_id']
                        );
                    } catch (Throwable $notificationError) {
                        error_log(
                            'Terminal Report Notification Error: '
                            . $notificationError->getMessage()
                        );
                    }
                }

                setFlash(
                    'success',
                    'Terminal report submitted successfully.'
                );

                redirect(
                    BASE_URL .
                    '/research/progress.php?id=' .
                    $researchId
                );

            } catch (Throwable $e) {

                $rolledBack = false;

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                    $rolledBack = true;
                }

                if (
                    $rolledBack
                    &&
                    $movedTerminalPath
                    &&
                    is_file($movedTerminalPath)
                ) {
                    unlink($movedTerminalPath);
                }

                error_log(
                    'Terminal Report Upload Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to submit the terminal report. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD PROOF OF PUBLICATION / PRESENTATION
    | REGULAR FOR-URRC PATH ONLY
    |--------------------------------------------------------------------------
    */

    if (
        $action
        === 'upload_publication_presentation_proof'
        &&
        !$errors
    ) {

        if ($isSelfInitiated) {

            $errors[] =
                'This proof upload action belongs to the regular research workflow.';

        } elseif (
            !$canUploadPublicationPresentationProof
            &&
            $isAssignedFaculty
            &&
            $hasPublicationPresentationProofDocument
            &&
            $workflowStatus === 'COMPLETED'
        ) {

            setFlash(
                'success',
                'Proof of publication or presentation is already submitted.'
            );

            redirect(
                BASE_URL
                . '/research/progress.php?id='
                . $researchId
            );
        }

        if (
            !$errors
            &&
            !$canUploadPublicationPresentationProof
        ) {

            $errors[] =
                'Proof submission is available only after the publication or presentation permit has been issued by RPS.';
        }

        $proofFile = null;

        if (!$errors) {

            $proofFile =
                progressValidatePublicationPresentationProofUpload(
                    'publication_presentation_proof_file',
                    $errors
                );
        }

        // The saved proof URL must match the link opened with the eye button.
        if (!$errors && $proofFile) {
            $viewSessionKey = $researchId . ':' . (int)$user['user_id'] . ':publication_presentation_proof';
            $lastViewedHash = $_SESSION['progress_other_document_views'][$viewSessionKey] ?? null;
            if (!is_string($lastViewedHash)
                || !hash_equals($lastViewedHash, hash('sha256', $proofFile['url']))) {
                $errors[] = 'Please click the eye button to view the proof link before submitting.';
            }
        }

        if (
            !$errors
            &&
            $proofFile
        ) {

            $movedProofPath = null;

            try {

                progressEnsureResearchDocumentTypeFlexible(
                    $pdo
                );

                $pdo->beginTransaction();

                $proofUpload =
                    progressMovePublicationPresentationProofUpload(
                        $proofFile,
                        $publicationPresentationProofUploadDirectory,
                        $researchId
                    );

                $movedProofPath =
                    $proofUpload[
                        'physical_path'
                    ];

                $proofDocumentType =
                    $permitProofTypeLabel
                    === 'Publication'
                        ? 'Proof of Publication'
                        : (
                            $permitProofTypeLabel
                            === 'Presentation'
                                ? 'Proof of Presentation'
                                : 'Proof of Publication/Presentation'
                        );

                $proofInsert =
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
                            ?, ?, ?, ?, ?, ?, ?, ?
                        )
                    ");

                $proofInsert->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $proofDocumentType,
                    $proofFile['original_name'],
                    $proofUpload['stored_name'],
                    $proofUpload['database_path'],
                    $proofFile['extension'],
                    $proofFile['size']
                ]);

                $proofUpdate =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = 'Completed',
                            workflow_status = 'COMPLETED',
                            workflow_stage = 'COMPLETED',
                            progress_percentage = 100,
                            completed_at =
                                COALESCE(
                                    completed_at,
                                    NOW()
                                ),
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND status = 'Ongoing'
                          AND workflow_status = 'PERMIT_ISSUED'

                          AND EXISTS
                          (
                              SELECT 1

                              FROM research_documents
                                   rd_terminal

                              WHERE
                                  rd_terminal.research_id =
                                  research_proposals.research_id

                                AND
                                  LOWER(
                                      TRIM(
                                          rd_terminal.document_type
                                      )
                                  )
                                  IN
                                  (
                                      'terminal report',
                                      'research terminal report',
                                      'final terminal report'
                                  )
                          )

                          AND EXISTS
                          (
                              SELECT 1

                              FROM research_status_history
                                   rsh_permit

                              LEFT JOIN users
                                   u_permit

                                  ON
                                   u_permit.user_id =
                                   rsh_permit.changed_by

                              WHERE
                                  rsh_permit.research_id =
                                  research_proposals.research_id

                                AND
                                  (
                                      u_permit.user_id IS NULL
                                      OR
                                      LOWER(
                                          TRIM(
                                              u_permit.role
                                          )
                                      ) = 'rps'
                                  )

                                AND
                                  LOWER(
                                      COALESCE(
                                          rsh_permit.remarks,
                                          ''
                                      )
                                  )
                                  LIKE
                                  '%permit for % issued%'
                          )
                    ");

                $proofUpdate->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);

                if (
                    $proofUpdate->rowCount()
                    !== 1
                ) {

                    throw new RuntimeException(
                        'Unable to complete the research workflow after proof submission.'
                    );
                }

                $proofHistory =
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
                            ?,
                            'Completed',
                            ?
                        )
                    ");

                $proofHistory->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    'Proof of '
                    . strtolower(
                        $permitProofTypeLabel
                    )
                    . ' link submitted by the researcher. Research workflow completed.'
                ]);

                $pdo->commit();
                unset($_SESSION['progress_other_document_views'][
                    $researchId . ':' . (int)$user['user_id'] . ':publication_presentation_proof'
                ]);

                if (
                    function_exists(
                        'notifyUsersByRole'
                    )
                ) {

                    try {

                        notifyUsersByRole(
                            $pdo,
                            [
                                'rps',
                                'admin'
                            ],
                            'Research proof submitted',
                            fullName($user)
                            . ' submitted proof of '
                            . strtolower(
                                $permitProofTypeLabel
                            )
                            . ' for '
                            . $research[
                                'research_title'
                            ]
                            . '. The research record is now completed.',
                            'research',
                            $researchId,
                            BASE_URL
                            . '/research/view.php?id='
                            . $researchId,
                            (int)$user['user_id']
                        );

                    } catch (
                        Throwable
                        $notificationError
                    ) {

                        error_log(
                            'Publication/Presentation Proof Notification Error: '
                            . $notificationError->getMessage()
                        );
                    }
                }

                setFlash(
                    'success',
                    'Proof of '
                    . strtolower(
                        $permitProofTypeLabel
                    )
                    . ' submitted successfully. Research marked as completed.'
                );

                redirect(
                    BASE_URL
                    . '/research/progress.php?id='
                    . $researchId
                );

            } catch (Throwable $e) {

                $rolledBack = false;

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();

                    $rolledBack = true;
                }

                if (
                    $rolledBack
                    &&
                    $movedProofPath
                    &&
                    is_file(
                        $movedProofPath
                    )
                ) {

                    unlink(
                        $movedProofPath
                    );
                }

                error_log(
                    'Publication/Presentation Proof Upload Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to submit the proof of publication or presentation. Please try again.';
            }
        }
    }


}


/*
|--------------------------------------------------------------------------
| RESEARCHER NAME
|--------------------------------------------------------------------------
*/

$researcherName =
    trim(
        $research['first_name']
        .
        ' '
        .
        (
            !empty(
                $research[
                    'middle_name'
                ]
            )
                ? $research[
                    'middle_name'
                ]
                . ' '
                : ''
        )
        .
        $research['last_name']
    );


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


/*
|--------------------------------------------------------------------------
| FILE SIZE
|--------------------------------------------------------------------------
*/

function progressFileSize(
    ?int $bytes
): string {

    if (
        !$bytes
        ||
        $bytes <= 0
    ) {

        return 'Unknown size';
    }


    if (
        $bytes >=
        1024 * 1024
    ) {

        return
            number_format(
                $bytes /
                (1024 * 1024),
                2
            )
            .
            ' MB';
    }


    if (
        $bytes >= 1024
    ) {

        return
            number_format(
                $bytes / 1024,
                2
            )
            .
            ' KB';
    }


    return
        number_format(
            $bytes
        )
        .
        ' bytes';
}


$flash = getFlash();

// Hide success banners on Project Monitoring, but keep error/warning notices.
// Consume the flash normally so successful POST redirects remain unchanged.
if (is_array($flash) && strtolower((string)($flash['type'] ?? '')) === 'success') {
    $flash = null;
}


require __DIR__ .
    '/../includes/header.php';

require __DIR__ .
    '/../includes/sidebar.php';

?>


<style>

/* =========================================================
   PAGE
========================================================= */

.progress-page {
    max-width: 1200px;
    margin: 0 auto;
}


.progress-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;
}


.progress-heading h1 {
    margin: 0 0 5px;

    font-size: 25px;
    font-weight: 700;
}


.progress-heading p {
    margin: 0;

    color: var(--asu-muted);

    font-size: 11px;
}


.progress-heading-actions {
    display: flex;
    gap: 8px;
}


/* =========================================================
   SUMMARY
========================================================= */

.project-summary {
    padding: 22px;

    margin-bottom: 20px;

    border-radius: 15px;

    background:
        linear-gradient(
            120deg,
            #08742c,
            #086126
        );

    color: #ffffff;
}


.project-summary-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;
}


.project-summary h2 {
    max-width: 800px;

    margin: 0 0 8px;

    font-size: 19px;
    font-weight: 700;

    line-height: 1.4;

    color: #ffffff;
}


.project-meta {
    display: flex;
    flex-wrap: wrap;

    gap: 8px 18px;

    color:
        rgba(
            255,
            255,
            255,
            .75
        );

    font-size: 10px;
}


.project-status {
    display: inline-flex;
    align-items: center;

    min-height: 28px;

    padding: 5px 11px;

    border-radius: 50px;

    background:
        rgba(
            255,
            255,
            255,
            .14
        );

    font-size: 9px;
    font-weight: 700;
}


/* =========================================================
   MAIN PROGRESS
========================================================= */

.main-progress {
    margin-top: 20px;
}


.main-progress-head {
    display: flex;
    justify-content: space-between;

    margin-bottom: 7px;

    font-size: 9px;
}


.main-progress-head strong {
    color: #ffd21c;

    font-size: 12px;
}


.main-progress-track {
    height: 8px;

    overflow: hidden;

    border-radius: 50px;

    background:
        rgba(
            255,
            255,
            255,
            .18
        );
}


.main-progress-value {
    height: 100%;

    border-radius: 50px;

    background: #ffd21c;
}


/* =========================================================
   GRID
========================================================= */

.progress-grid {
    display: grid;

    grid-template-columns: 1fr;

    gap: 20px;

    align-items: start;
}


.progress-grid > .progress-column:first-child {
    order: 2;
}


.progress-grid > .progress-column:last-child {
    order: 1;
}


.progress-column {
    display: grid;

    gap: 20px;
}


/* =========================================================
   PANEL
========================================================= */

.progress-panel {
    overflow: hidden;

    border:
        1px solid
        var(--asu-border);

    border-radius: 14px;

    background: #ffffff;
}


.progress-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    min-height: 59px;

    padding: 15px 20px;

    border-bottom:
        1px solid #edf1ed;
}


.progress-panel-title {
    display: flex;
    align-items: center;

    gap: 9px;

    margin: 0;

    color: #293129;

    font-size: 13px;
    font-weight: 700;
}


.progress-panel-icon {
    width: 34px;
    height: 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: #edf7ef;

    color: var(--asu-green);

    font-size: 15px;
}


.progress-panel-body {
    padding: 20px;
}


/* =========================================================
   FORM
========================================================= */

.progress-form {
    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );

    gap: 16px;
}


.progress-form .mb-3 {
    margin-bottom: 0 !important;
}


.progress-form .mb-3:nth-of-type(1),
.progress-form .mb-3:nth-of-type(4),
.progress-form .mb-3:nth-of-type(5),
.progress-form .mb-3:nth-of-type(6) {

    grid-column: 1 / -1;
}


.progress-form button[type="submit"] {
    grid-column: 1 / -1;
}


.progress-form
.form-label {

    margin-bottom: 6px;

    color: #555f57;

    font-size: 10px;
    font-weight: 600;
}


.progress-form
.form-control,
.progress-form
.form-select {

    min-height: 42px;

    font-size: 11px;
}


.progress-form textarea {
    min-height: 105px;

    resize: vertical;
}


.form-note {
    margin-top: 5px;

    color: #89918b;

    font-size: 8px;
}


.percentage-box {
    display: flex;
    align-items: center;

    gap: 12px;
}


.percentage-box input {
    flex: 1;
}


.percentage-value {
    min-width: 50px;

    padding: 8px 9px;

    border-radius: 7px;

    background: #edf7ef;

    color: var(--asu-green);

    font-size: 11px;
    font-weight: 700;

    text-align: center;
}


/* =========================================================
   HISTORY
========================================================= */

.progress-history {
    display: grid;

    gap: 14px;
}


.progress-update {
    position: relative;

    padding: 17px;

    border:
        1px solid
        #e4e9e5;

    border-radius: 11px;

    background: #fbfcfb;
}


.progress-update-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 15px;

    margin-bottom: 12px;
}


.progress-update h3 {
    margin: 0 0 4px;

    color: #303830;

    font-size: 12px;
    font-weight: 700;
}


.progress-update-date {
    color: #89918b;

    font-size: 8px;
}


.progress-percent {
    min-width: 49px;

    padding: 6px 8px;

    border-radius: 50px;

    background: #e5f4e8;

    color: #287143;

    font-size: 9px;
    font-weight: 700;

    text-align: center;
}


.update-track {
    height: 5px;

    margin-bottom: 14px;

    overflow: hidden;

    border-radius: 50px;

    background: #e6ebe7;
}


.update-track div {
    height: 100%;

    border-radius: 50px;

    background: var(--asu-green);
}


.update-section {
    margin-top: 12px;
}


.update-section strong {
    display: block;

    margin-bottom: 4px;

    color: #4b554d;

    font-size: 9px;
}


.update-section p {
    margin: 0;

    color: #687169;

    font-size: 9px;

    line-height: 1.65;

    white-space: pre-line;
}


/* =========================================================
   DOCUMENT
========================================================= */

.progress-document {
    display: flex;
    align-items: center;

    gap: 10px;

    margin-top: 14px;

    padding: 10px;

    border-radius: 8px;

    background: #edf7ef;
}


.progress-document i {
    color: var(--asu-green);

    font-size: 17px;
}


.progress-document strong {
    display: block;

    overflow: hidden;

    color: #39433b;

    font-size: 9px;

    text-overflow: ellipsis;
    white-space: nowrap;
}


.progress-document small {
    color: #7e8880;

    font-size: 8px;
}


/* =========================================================
   PROJECT INFORMATION
========================================================= */

.project-info {
    display: grid;

    gap: 15px;
}


.project-info-item label {
    display: block;

    margin-bottom: 4px;

    color: #8a938c;

    font-size: 8px;
    font-weight: 600;

    text-transform: uppercase;
}


.project-info-item strong {
    display: block;

    color: #3a433c;

    font-size: 10px;

    line-height: 1.5;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-progress {
    padding: 30px 15px;

    text-align: center;

    color: #879089;
}


.empty-progress i {
    display: block;

    margin-bottom: 10px;

    color: #b1bbb3;

    font-size: 28px;
}


.empty-progress strong {
    display: block;

    margin-bottom: 4px;

    color: #616b63;

    font-size: 11px;
}


.empty-progress span {
    font-size: 9px;
}


/* =========================================================
   ERROR
========================================================= */

.progress-errors {
    margin-bottom: 18px;

    padding: 14px 17px;

    border:
        1px solid #edcccc;

    border-radius: 10px;

    background: #fff6f6;

    color: #873b3b;

    font-size: 10px;
}


.design-notice {
    padding: 16px 18px;
    border: 1px solid #dfe9e1;
    border-radius: 14px;
    background: #ffffff;
    color: #647069;
    font-size: 12px;
    line-height: 1.6;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width: 1000px
) {

    .progress-grid {
        grid-template-columns: 1fr;
    }

}


@media (
    max-width: 700px
) {

    .progress-heading,
    .project-summary-top {

        flex-direction: column;
    }


    .progress-heading-actions {
        width: 100%;

        flex-direction: column;
    }


    .progress-heading-actions .btn {
        width: 100%;
    }


    .progress-form {
        grid-template-columns: 1fr;
    }

}



/* =========================================================
   COMPACT PROFESSIONAL PROGRESS HISTORY
   - smaller cards
   - removes huge whitespace from saved text
   - keeps history readable without eating the whole page
========================================================= */

.progress-history-panel {
    border-radius: 16px;
}

.progress-history-panel .progress-panel-header {
    min-height: 54px;
    padding: 13px 18px;
}

.progress-history-panel .progress-panel-title {
    gap: 10px;
    font-size: 13px;
}

.progress-history-panel .progress-panel-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    font-size: 14px;
}

.progress-count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 5px 12px;
    border-radius: 999px;
    background: #f4faf5;
    color: #657068;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.progress-history-panel .progress-panel-body {
    max-height: 520px;
    overflow-y: auto;
    padding: 14px;
}

.progress-history-panel .progress-panel-body::-webkit-scrollbar {
    width: 7px;
}

.progress-history-panel .progress-panel-body::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #d9e6dc;
}

.progress-history {
    gap: 10px;
}

.progress-update {
    padding: 13px 15px;
    border-color: #e8eee9;
    border-radius: 13px;
    background: #ffffff;
    box-shadow: 0 8px 18px rgba(19, 44, 27, .035);
}

.progress-update-top {
    align-items: center;
    gap: 12px;
    margin-bottom: 9px;
}

.progress-update h3 {
    margin: 0 0 3px;
    color: #253126;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.25;
}

.progress-update-date {
    color: #7c877f;
    font-size: 10px;
    line-height: 1.3;
}

.progress-percent {
    min-width: 48px;
    padding: 5px 9px;
    border-radius: 999px;
    background: #eef8f0;
    color: var(--asu-green);
    font-size: 10px;
    font-weight: 750;
}

.update-track {
    height: 5px;
    margin-bottom: 10px;
    background: #edf1ed;
}

.update-section {
    display: grid;
    grid-template-columns: 145px minmax(0, 1fr);
    gap: 10px;
    margin-top: 0;
    padding: 9px 0;
    border-top: 1px solid #eef2ee;
}

.update-track + .update-section {
    border-top: 0;
    padding-top: 3px;
}

.update-section strong {
    margin: 0;
    color: #56625a;
    font-size: 9px;
    font-weight: 750;
    line-height: 1.35;
    text-transform: uppercase;
    letter-spacing: .02em;
}

.update-section p {
    margin: 0;
    color: #434d46;
    font-size: 10px;
    line-height: 1.45;
    white-space: normal;
    overflow-wrap: anywhere;
}

.progress-document {
    gap: 9px;
    margin-top: 8px;
    padding: 9px 10px;
    border: 1px solid #dfeee3;
    border-radius: 11px;
    background: #f4fbf6;
}

.progress-document i {
    font-size: 16px;
}

.progress-document strong {
    max-width: 100%;
    color: #2f3b33;
    font-size: 10px;
    line-height: 1.3;
}

.progress-document small {
    font-size: 9px;
}

@media (max-width: 700px) {
    .progress-history-panel .progress-panel-body {
        max-height: none;
    }

    .update-section {
        grid-template-columns: 1fr;
        gap: 4px;
    }
}



/* =========================================================
   COMPLETED RESEARCH MANUSCRIPT
========================================================= */

.completed-manuscript-card .completed-manuscript-body {
    display: grid;
    gap: 14px;
}

.completed-manuscript-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px;
    border: 1px solid rgba(0, 107, 46, .14);
    border-radius: 13px;
    background: #f7fbf8;
}

.completed-manuscript-file {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.completed-manuscript-file i {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: rgba(0, 107, 46, .10);
    color: var(--asu-green);
    font-size: 17px;
}

.completed-manuscript-file strong {
    display: block;
    max-width: 520px;
    overflow: hidden;
    color: #273328;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.completed-manuscript-file small {
    color: #6e796f;
    font-size: 10px;
}

.completed-manuscript-form {
    display: grid;
    gap: 12px;
}

.completed-manuscript-form .form-control {
    min-height: 42px;
    font-size: 11px;
}

.completed-manuscript-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.uniform-link-upload-card {
    display: grid;
    grid-template-columns: minmax(220px, 290px) minmax(0, 1fr);
    align-items: center;
    gap: 18px;
    padding: 16px 18px;
    box-sizing: border-box;
    border: 1px solid rgba(0, 122, 51, .18);
    border-radius: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
}

.uniform-link-upload-head {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
}

.uniform-link-upload-icon {
    width: 54px;
    height: 54px;
    flex: 0 0 54px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background: #edf8f0;
    color: var(--asu-green);
    font-size: 22px;
}

.uniform-link-upload-title {
    margin: 0;
    color: #101810;
    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.35;
}

.uniform-link-upload-required {
    color: #d65555;
    font-weight: 800;
}

.uniform-link-upload-fields {
    width: 100%;
    min-width: 0;
    display: grid;
    gap: 7px;
}

/* Keep the eye button flush with the field's right edge: no unused grid space. */
.progress-page .uniform-link-upload-row {
    display: flex !important;
    align-items: stretch;
    gap: 10px !important;
    width: 100%;
    min-width: 0;
}

.progress-page .uniform-link-upload-row > .form-control {
    flex: 1 1 0 !important;
    width: 0 !important;
    min-width: 0 !important;
    height: 48px !important;
    min-height: 48px !important;
    border-radius: 12px !important;
}

.progress-page .uniform-link-upload-row > .completed-manuscript-view-button {
    flex: 0 0 60px !important;
    width: 60px !important;
    min-width: 60px !important;
    height: 48px !important;
    min-height: 48px !important;
    max-height: 48px !important;
    padding: 0 !important;
    border-radius: 12px !important;
    font-size: 19px !important;
}

.uniform-link-upload-note {
    margin: 0;
    color: #6c786f;
    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 9px;
    line-height: 1.55;
}

.uniform-link-upload-note.rems-upload-info-note {
    display: flex !important;
    align-items: flex-start;
    gap: 7px;
}

.uniform-link-upload-note.rems-upload-info-note::before {
    margin-top: 1px;
}

@media (max-width: 900px) {
    .uniform-link-upload-card {
        grid-template-columns: 1fr;
        gap: 14px;
        padding: 16px;
    }

    .uniform-link-upload-head {
        align-items: center;
    }
}

@media (max-width: 700px) {
    .completed-manuscript-box {
        align-items: stretch;
        flex-direction: column;
    }

    .completed-manuscript-actions,
    .completed-manuscript-box .btn {
        width: 100%;
    }

    .progress-page .uniform-link-upload-row {
        gap: 8px !important;
    }

    .progress-page .uniform-link-upload-row > .completed-manuscript-view-button {
        flex-basis: 52px !important;
        width: 52px !important;
        min-width: 52px !important;
        height: 46px !important;
        min-height: 46px !important;
        max-height: 46px !important;
    }

    .progress-page .uniform-link-upload-row > .form-control {
        height: 46px !important;
        min-height: 46px !important;
    }
}


/* =========================================================
   ASU PROFESSIONAL PALETTE POLISH
   Green dominant • Yellow secondary • Orange notifications
   Design-only override: no workflow/database logic changed.
========================================================= */

:root {
    --asu-green: #006B2E;
    --asu-green-dark: #004F22;
    --asu-green-deep: #003F1A;
    --asu-yellow: #FFD21F;
    --asu-yellow-dark: #E5B800;
    --asu-orange: #F28C28;
    --asu-orange-dark: #C96800;
    --asu-white: #FFFFFF;
    --asu-black: #111711;
    --asu-bg: #F7FAF4;
    --asu-card: #FFFFFF;
    --asu-border: rgba(0, 107, 46, .16);
    --asu-muted: #4C554F;
}

html,
body {
    color: var(--asu-black) !important;
    background:
        radial-gradient(circle at top right, rgba(255, 210, 31, .10), transparent 32%),
        linear-gradient(180deg, #FFFFFF 0%, #F7FAF4 100%) !important;
}

.page-content {
    background:
        radial-gradient(circle at top right, rgba(255, 210, 31, .10), transparent 34%),
        linear-gradient(180deg, #FAFCF8 0%, #F4F8F1 100%) !important;
}

.system-name,
.topbar-user-text strong,
.research-view-heading h1,
.progress-heading h1,
.monitoring-hero h1,
.rps-queue-heading h1,
.processing-hero h1,
.notification-header-row h1,
.card-title,
.research-panel-title,
.workflow-text h4,
.notification-title-line h3 {
    color: var(--asu-black) !important;
}

.topbar-user-text span,
.research-view-heading p,
.progress-heading p,
.monitoring-hero p,
.rps-queue-heading p,
.processing-hero p,
.notification-header-row p,
.info-item label,
.notification-content p,
.notification-meta {
    color: var(--asu-muted) !important;
}

.btn-asu,
.btn.btn-asu {
    background: var(--asu-green) !important;
    border-color: var(--asu-green) !important;
    color: #FFFFFF !important;
    box-shadow: 0 10px 20px rgba(0, 107, 46, .18) !important;
}

.btn-asu:hover,
.btn.btn-asu:hover {
    background: var(--asu-green-dark) !important;
    border-color: var(--asu-green-dark) !important;
    color: #FFFFFF !important;
}

.btn-outline-asu,
.btn.btn-outline-asu {
    background: #FFFFFF !important;
    border-color: rgba(0, 107, 46, .28) !important;
    color: var(--asu-green) !important;
}

.btn-outline-asu:hover,
.btn.btn-outline-asu:hover {
    background: rgba(0, 107, 46, .06) !important;
    border-color: var(--asu-green) !important;
    color: var(--asu-green-deep) !important;
}

.research-panel,
.workflow-card,
.research-document-viewer,
.processing-card,
.processing-queue-card,
.rps-queue-table-wrap,
.notification-summary-card,
.notification-filter-card,
.notification-list-card,
.monitoring-filter-card,
.monitoring-project-card,
.project-info-card,
.progress-form-card,
.progress-history-card,
.progress-update-card,
.card,
.asu-card {
    background: #FFFFFF !important;
    border: 1px solid rgba(0, 107, 46, .14) !important;
    box-shadow: 0 14px 34px rgba(0, 79, 34, .06) !important;
}

.research-panel-icon,
.research-status-overview-icon,
.processing-queue-icon,
.processing-card-icon,
.rps-queue-icon,
.monitoring-icon,
.notification-summary-card .notification-icon,
.notification-icon,
.progress-panel-icon,
.project-info-icon,
.document-icon,
.document-open-icon {
    background: rgba(0, 107, 46, .08) !important;
    color: var(--asu-green) !important;
}

.processing-queue-icon.yellow,
.notification-icon.yellow {
    background: rgba(255, 210, 31, .24) !important;
    color: var(--asu-green-deep) !important;
}

.notification-icon.blue,
.notification-icon.orange,
.notification-summary-card .notification-icon {
    background: rgba(242, 140, 40, .14) !important;
    color: var(--asu-orange-dark) !important;
}

.notification-unread-dot {
    background: var(--asu-orange) !important;
}

.notification-item.unread {
    border-color: rgba(242, 140, 40, .42) !important;
    background: #FFFBF5 !important;
}

.notification-item:hover,
.processing-queue-link:hover,
.rps-queue-table tbody tr:hover td,
.monitoring-project-card:hover {
    border-color: rgba(0, 107, 46, .28) !important;
    box-shadow: 0 18px 40px rgba(0, 79, 34, .10) !important;
}

.notification-tab.active,
.rps-queue-tab.active,
.processing-total-badge,
.rps-queue-badge,
.monitoring-stat-badge {
    background: rgba(255, 210, 31, .22) !important;
    border-color: rgba(0, 107, 46, .20) !important;
    color: var(--asu-green-deep) !important;
}

.processing-count,
.monitoring-count,
.rps-workflow-badge,
.monitoring-status,
.project-status,
.research-status {
    border-color: rgba(0, 107, 46, .18) !important;
    color: var(--asu-green-deep) !important;
}

.status-draft {
    background: #FFFFFF !important;
    color: var(--asu-black) !important;
    border-color: rgba(0, 107, 46, .18) !important;
}

.status-submitted,
.status-review,
.status-under-review,
.status-pending {
    background: rgba(255, 210, 31, .36) !important;
    color: var(--asu-black) !important;
    border-color: rgba(255, 210, 31, .70) !important;
}

.status-revision,
.status-for-revision {
    background: rgba(242, 140, 40, .16) !important;
    color: var(--asu-black) !important;
    border-color: rgba(242, 140, 40, .38) !important;
}

.status-approved,
.status-ongoing,
.status-completed {
    background: var(--asu-green) !important;
    color: #FFFFFF !important;
    border-color: var(--asu-green) !important;
}

.status-rejected,
.status-archived {
    background: var(--asu-black) !important;
    color: #FFFFFF !important;
    border-color: var(--asu-black) !important;
}

/* Professional green status/progress bar */
.research-summary,
.project-summary,
.monitoring-hero {
    background:
        radial-gradient(circle at 95% 0%, rgba(255, 210, 31, .22), transparent 30%),
        linear-gradient(135deg, #007A34 0%, #006B2E 54%, #004F22 100%) !important;
    border: 1px solid rgba(255, 255, 255, .10) !important;
    color: #FFFFFF !important;
    box-shadow: 0 18px 42px rgba(0, 79, 34, .18) !important;
}

.research-summary h2,
.project-summary h2,
.monitoring-hero h1,
.research-summary .research-summary-meta,
.project-summary .project-summary-meta,
.monitoring-hero p {
    color: #FFFFFF !important;
}

.research-summary-meta,
.project-summary-meta {
    color: rgba(255, 255, 255, .84) !important;
}

.research-summary-meta i,
.project-summary-meta i,
.workflow-stage-line i,
.main-progress-head span,
.monitoring-hero i {
    color: var(--asu-yellow) !important;
}

.workflow-status-strip .research-progress-head strong,
.main-progress-head strong,
.monitoring-progress-top strong,
.project-summary .project-status,
.research-summary .research-status {
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
    border-color: rgba(255, 255, 255, .20) !important;
}

.research-summary .research-progress-bar,
.main-progress-track,
.monitoring-progress-track {
    background: rgba(255, 255, 255, .23) !important;
}

.research-summary .research-progress-value,
.main-progress-value,
.monitoring-progress-value {
    background: var(--asu-yellow) !important;
}

.workflow-card {
    border-left: 4px solid var(--asu-green) !important;
}

.workflow-card.is-warning,
.workflow-notice {
    border-left-color: var(--asu-orange) !important;
    background: rgba(242, 140, 40, .08) !important;
    color: var(--asu-black) !important;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--asu-green) !important;
    box-shadow: 0 0 0 .18rem rgba(0, 107, 46, .14) !important;
}



/* =========================================================
   ADDITIVE SELF-INITIATED FINAL WORKFLOW
   Existing progress.php sections are intentionally preserved.
========================================================= */

.self-final-workflow {
    margin-bottom: 20px;
}

.self-final-workflow .progress-panel-header {
    align-items: center;
}

.self-final-workflow-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    min-height: 28px;
    padding: 5px 10px;

    border-radius: 999px;

    background: #eaf7ee;
    color: #08752c;

    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 9px;
    font-weight: 700;
}

.self-final-intro {
    margin: 0 0 18px;

    color: #6d786f;

    font-size: 10.5px;
    line-height: 1.7;
}

.self-final-steps {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.self-final-step {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) auto;
    align-items: center;
    gap: 14px;

    padding: 16px;

    border: 1px solid #dfe9e2;
    border-radius: 14px;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fbfdfb 100%
        );
}

.self-final-step-number {
    width: 42px;
    height: 42px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;

    background: #eaf7ee;
    color: #08752c;

    font-size: 13px;
    font-weight: 800;
}

.self-final-step-content {
    min-width: 0;
}

.self-final-step-title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;

    margin-bottom: 5px;
}

.self-final-step-title h3 {
    margin: 0;

    color: #1d2821;

    font-size: 12px;
    font-weight: 700;
}

.self-final-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;

    padding: 4px 8px;

    border-radius: 999px;

    background: #edf8f0;
    color: #08752c;

    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 8px;
    font-weight: 700;
}

.self-final-step p {
    margin: 0;

    color: #768079;

    font-size: 9.5px;
    line-height: 1.55;
}

.self-final-files {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;

    margin-top: 10px;
}

.self-final-file {
    min-width: 0;

    display: inline-flex;
    align-items: center;
    gap: 8px;

    padding: 8px 10px;

    border: 1px solid #e0e7e2;
    border-radius: 10px;

    background: #ffffff;
}

.self-final-file-icon {
    color: #08752c;
    font-size: 16px;
}

.self-final-file-text {
    min-width: 0;
}

.self-final-file-text strong {
    display: block;
    max-width: 430px;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    color: #273229;

    font-size: 9.5px;
}

.self-final-file-text small {
    display: block;
    margin-top: 2px;

    color: #879089;

    font-size: 8px;
}

.self-final-step-actions {
    display: flex;
    justify-content: flex-end;
    gap: 7px;
}

.self-final-step-actions .btn {
    white-space: nowrap;
}

.self-final-complete {
    margin-top: 14px;
    padding: 15px 17px;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;

    border: 1px solid #cde5d4;
    border-radius: 14px;

    background: #f2faf4;
}

.self-final-complete strong {
    color: #08752c;
    font-size: 11.5px;
}

.self-final-complete span {
    color: #68736b;
    font-size: 9.5px;
}

@media (max-width: 760px) {

    .self-final-step {
        grid-template-columns:
            40px
            minmax(0, 1fr);
    }

    .self-final-step-actions {
        grid-column: 1 / -1;
        justify-content: stretch;
    }

    .self-final-step-actions .btn {
        width: 100%;
    }

    .self-final-complete {
        align-items: flex-start;
        flex-direction: column;
    }
}



/* =========================================================
   UNIFORM REMS DESIGN SYSTEM — RESEARCH PROGRESS
   UI ONLY — workflow/database/actions untouched
========================================================= */

:root {
    --rems-green: #007a33;
    --rems-green-dark: #005c26;
    --rems-green-deep: #164b2a;
    --rems-yellow: #f1c93b;
    --rems-gold: #d3aa35;
    --rems-text: #1f2922;
    --rems-muted: #6d786f;
    --rems-border: rgba(0, 107, 46, .13);
    --rems-card: #ffffff;
    --rems-soft: #f7faf7;
    --rems-shadow: 0 12px 30px rgba(23, 67, 37, .055);
}


/* ---------------------------------------------------------
   PAGE BACKGROUND / WIDTH
--------------------------------------------------------- */

html,
body {
    font-family: "Century Gothic", Arial, sans-serif !important;
}

.page-content {
    padding: 24px 28px 36px !important;

    background:
        radial-gradient(
            circle at 88% 8%,
            rgba(255, 211, 59, .12),
            transparent 24%
        ),
        radial-gradient(
            circle at 10% 32%,
            rgba(0, 122, 51, .08),
            transparent 26%
        ),
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

.progress-page {
    width: 100% !important;
    max-width: 1320px !important;
    margin: 0 auto !important;
}


/* ---------------------------------------------------------
   COMPACT MODULE BANNER
   Matches the main REMS interface banner.
--------------------------------------------------------- */

.progress-heading {
    position: relative !important;
    overflow: hidden !important;

    min-height: 96px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 18px !important;

    margin: 0 0 20px !important;
    padding: 18px 22px !important;

    border: 1px solid rgba(255, 255, 255, .12) !important;
    border-radius: 18px !important;

    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #2c9a39 100%
        ) !important;

    box-shadow:
        0 14px 30px rgba(0, 92, 38, .16) !important;
}

.progress-heading::after {
    content: "" !important;

    position: absolute !important;
    top: -52px !important;
    right: -34px !important;

    width: 210px !important;
    height: 210px !important;

    border-radius: 50% !important;

    background:
        radial-gradient(
            circle,
            rgba(255, 210, 31, .20) 0%,
            rgba(255, 255, 255, .08) 46%,
            transparent 47%
        ) !important;

    pointer-events: none !important;
}

.progress-heading > div {
    position: relative !important;
    z-index: 2 !important;
}

.progress-heading h1 {
    margin: 0 0 4px !important;

    color: #ffffff !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 30px !important;
    font-weight: 800 !important;
    line-height: 1.15 !important;
    letter-spacing: -.025em !important;
}

.progress-heading p {
    margin: 0 !important;

    color: rgba(255, 255, 255, .82) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    line-height: 1.55 !important;
}

.progress-heading-actions {
    position: relative !important;
    z-index: 2 !important;

    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
}

.progress-heading-actions .btn {
    min-height: 40px !important;
    padding: 0 13px !important;

    border-radius: 10px !important;

    background: rgba(255, 255, 255, .96) !important;
    border-color: rgba(255, 255, 255, .95) !important;
    color: var(--rems-green-deep) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 800 !important;

    box-shadow: 0 8px 18px rgba(0, 65, 27, .10) !important;
}

.progress-heading-actions .btn:hover {
    background: #ffffff !important;
    border-color: #ffffff !important;
    color: var(--rems-green) !important;
}


/* ---------------------------------------------------------
   FLASH / ERROR FEEDBACK
--------------------------------------------------------- */

.progress-page .alert,
.progress-errors {
    margin-bottom: 18px !important;
    padding: 12px 15px !important;

    border-radius: 12px !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    line-height: 1.6 !important;

    box-shadow: none !important;
}

.progress-page .alert-success {
    border-color: #cfe4d5 !important;
    background: #f4faf6 !important;
    color: #285f39 !important;
}

.progress-page .alert-warning {
    border-color: #e9ddbb !important;
    background: #fdfaf2 !important;
    color: #6b5927 !important;
}

.progress-page .alert-danger,
.progress-errors {
    border-color: #ead0d0 !important;
    background: #fdf7f7 !important;
    color: #783d3d !important;
}


/* ---------------------------------------------------------
   PROJECT SUMMARY — CLEAN STATUS CARD
--------------------------------------------------------- */

.project-summary {
    margin-bottom: 18px !important;
    padding: 20px 22px !important;

    border: 1px solid var(--rems-border) !important;
    border-radius: 18px !important;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fbfdfb 100%
        ) !important;

    color: var(--rems-text) !important;

    box-shadow: var(--rems-shadow) !important;
}

.project-summary-top {
    gap: 18px !important;
}

.project-summary h2 {
    margin: 0 0 8px !important;

    color: var(--rems-text) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 20px !important;
    font-weight: 800 !important;
    line-height: 1.4 !important;
}

.project-meta {
    gap: 7px 16px !important;

    color: var(--rems-muted) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    font-weight: 600 !important;
}

.project-meta i {
    color: var(--rems-green) !important;
}

.project-status {
    min-height: 30px !important;
    padding: 6px 11px !important;

    border: 1px solid rgba(0, 122, 51, .18) !important;
    border-radius: 999px !important;

    background: #edf8f0 !important;
    color: var(--rems-green-dark) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 700 !important;
}


/* ---------------------------------------------------------
   MAIN PROGRESS BAR
--------------------------------------------------------- */

.main-progress {
    margin-top: 18px !important;
    padding-top: 14px !important;

    border-top: 1px solid #edf1ee !important;
}

.main-progress-head {
    margin-bottom: 7px !important;

    color: #68736b !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 700 !important;
}

.main-progress-head strong {
    padding: 0 !important;

    background: transparent !important;
    color: var(--rems-green) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 12px !important;
    font-weight: 800 !important;
}

.main-progress-head small {
    color: #88918b !important;
}

.main-progress-track {
    height: 8px !important;

    border-radius: 999px !important;

    background: #e8eee9 !important;
}

.main-progress-value {
    border-radius: 999px !important;

    background:
        linear-gradient(
            90deg,
            var(--rems-green) 0%,
            #2d9840 100%
        ) !important;
}


/* ---------------------------------------------------------
   PANELS / CARDS
--------------------------------------------------------- */

.progress-grid {
    gap: 18px !important;
}

.progress-column {
    gap: 18px !important;
}

.progress-panel,
.self-final-workflow {
    overflow: hidden !important;

    border: 1px solid var(--rems-border) !important;
    border-radius: 18px !important;

    background: #ffffff !important;

    box-shadow: var(--rems-shadow) !important;
}

.progress-panel-header {
    min-height: 58px !important;
    padding: 14px 18px !important;

    border-bottom: 1px solid #edf1ee !important;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fbfdfb 100%
        ) !important;
}

.progress-panel-title {
    gap: 10px !important;

    color: #263129 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 13px !important;
    font-weight: 800 !important;
    letter-spacing: -.01em !important;
}

.progress-panel-icon {
    width: 38px !important;
    height: 38px !important;

    border-radius: 12px !important;

    background: #edf8f0 !important;
    color: var(--rems-green) !important;

    font-size: 16px !important;
}

.progress-panel-body {
    padding: 18px !important;
}


/* ---------------------------------------------------------
   SELF-INITIATED FINAL WORKFLOW
--------------------------------------------------------- */

.self-final-workflow {
    margin-bottom: 18px !important;
}

.self-final-workflow-badge {
    min-height: 28px !important;
    padding: 5px 10px !important;

    border: 1px solid #d7eadc !important;

    background: #edf8f0 !important;
    color: var(--rems-green-dark) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
}

.self-final-intro {
    margin-bottom: 16px !important;

    color: var(--rems-muted) !important;

    font-size: 10px !important;
    line-height: 1.7 !important;
}

.self-final-steps {
    gap: 10px !important;
}

.self-final-step {
    grid-template-columns: 46px minmax(0, 1fr) auto !important;
    gap: 14px !important;

    padding: 15px !important;

    border: 1px solid #e1e9e3 !important;
    border-radius: 14px !important;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fbfdfb 100%
        ) !important;

    box-shadow: 0 6px 16px rgba(18, 56, 29, .025) !important;
}

.self-final-step-number {
    width: 42px !important;
    height: 42px !important;

    border-radius: 12px !important;

    background:
        linear-gradient(
            135deg,
            #eaf7ee 0%,
            #f3faf4 100%
        ) !important;

    color: var(--rems-green) !important;

    font-size: 12px !important;
}

.self-final-step-title {
    margin-bottom: 4px !important;
}

.self-final-step-title h3 {
    color: #263129 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 11.5px !important;
    font-weight: 800 !important;
}

.self-final-status {
    padding: 4px 8px !important;

    border: 1px solid #d9ebde !important;
    border-radius: 999px !important;

    background: #f2faf4 !important;
    color: var(--rems-green-dark) !important;

    font-size: 7.8px !important;
}

.self-final-step p {
    color: #747f77 !important;

    font-size: 9.3px !important;
}

.self-final-file {
    padding: 8px 10px !important;

    border: 1px solid #e1e8e3 !important;
    border-radius: 10px !important;

    background: #ffffff !important;
}

.self-final-file-icon {
    color: var(--rems-green) !important;
}

.self-final-complete {
    margin-top: 12px !important;
    padding: 14px 16px !important;

    border: 1px solid #d1e7d7 !important;
    border-radius: 13px !important;

    background: #f2faf4 !important;
}

.self-final-complete strong {
    color: var(--rems-green-dark) !important;
}


/* ---------------------------------------------------------
   PROGRESS HISTORY
--------------------------------------------------------- */

.progress-history-panel .progress-panel-body {
    max-height: none !important;
    overflow: visible !important;
    padding: 14px 16px !important;
}

.progress-count-pill {
    min-height: 27px !important;
    padding: 5px 10px !important;

    border: 1px solid #dce8df !important;
    border-radius: 999px !important;

    background: #f5faf6 !important;
    color: #69746c !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 700 !important;
}

.progress-history {
    gap: 10px !important;
}

.progress-update {
    padding: 14px 15px !important;

    border: 1px solid #e5ebe7 !important;
    border-radius: 13px !important;

    background: #ffffff !important;

    box-shadow: 0 6px 14px rgba(20, 51, 29, .025) !important;
}

.progress-update h3 {
    color: #273229 !important;

    font-size: 11.5px !important;
    font-weight: 800 !important;
}

.progress-update-date {
    color: #7c877f !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
}

.progress-percent {
    border: 1px solid #d8ebdd !important;
    border-radius: 999px !important;

    background: #edf8f0 !important;
    color: var(--rems-green) !important;

    font-size: 9px !important;
}

.update-track {
    background: #edf1ee !important;
}

.update-track div {
    background: var(--rems-green) !important;
}

.update-section strong {
    color: #59645c !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.3px !important;
}

.update-section p {
    color: #475249 !important;

    font-size: 9.7px !important;
    line-height: 1.55 !important;
}


/* ---------------------------------------------------------
   PROJECT INFORMATION
--------------------------------------------------------- */

.project-info {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 12px 14px !important;
}

.project-info-item {
    min-width: 0 !important;
    padding: 12px 13px !important;

    border: 1px solid #e4ebe5 !important;
    border-radius: 12px !important;

    background: #fbfdfb !important;
}

.project-info-item label {
    margin-bottom: 5px !important;

    color: #7c867f !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8px !important;
    font-weight: 700 !important;
    letter-spacing: .04em !important;
}

.project-info-item strong {
    color: #2f3932 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    line-height: 1.5 !important;
}


/* ---------------------------------------------------------
   COMPLETED MANUSCRIPT / TERMINAL REPORT
--------------------------------------------------------- */

.completed-manuscript-card .completed-manuscript-body {
    gap: 12px !important;
}

.completed-manuscript-box {
    padding: 13px 14px !important;

    border: 1px solid #dfe9e2 !important;
    border-radius: 13px !important;

    background:
        linear-gradient(
            180deg,
            #fbfdfb 0%,
            #f7fbf8 100%
        ) !important;
}

.completed-manuscript-file i {
    width: 40px !important;
    height: 40px !important;

    border-radius: 12px !important;

    background: #eaf7ee !important;
    color: var(--rems-green) !important;
}

.completed-manuscript-file strong {
    color: #263129 !important;

    font-size: 11px !important;
    font-weight: 700 !important;
}

.completed-manuscript-file small {
    color: #737e76 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
}


/* ---------------------------------------------------------
   FORMS / INPUTS
--------------------------------------------------------- */

.progress-page .form-label {
    margin-bottom: 6px !important;

    color: #4f5a52 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9px !important;
    font-weight: 700 !important;
}

.progress-page .form-control,
.progress-page .form-select {
    min-height: 43px !important;

    border: 1px solid #d7e0d8 !important;
    border-radius: 10px !important;

    background: #ffffff !important;
    color: var(--rems-text) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
}

.progress-page textarea.form-control {
    min-height: 104px !important;
}

.progress-page .form-control:focus,
.progress-page .form-select:focus {
    border-color: #76ac82 !important;
    box-shadow: 0 0 0 .18rem rgba(118, 172, 130, .13) !important;
}

.form-note {
    margin-top: 5px !important;

    color: #8a938c !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8px !important;
}


/* ---------------------------------------------------------
   BUTTONS
--------------------------------------------------------- */

.progress-page .btn:not(.btn-sm) {
    min-height: 40px !important;
    padding: 0 13px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;

    border-radius: 10px !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 800 !important;

    box-shadow: none !important;
}

.progress-page .btn-sm {
    min-height: 34px !important;
    padding: 0 10px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 9px !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 800 !important;
}

.progress-page .btn-asu,
.progress-page .btn.btn-asu {
    background: var(--rems-green) !important;
    border-color: var(--rems-green) !important;
    color: #ffffff !important;

    box-shadow: 0 7px 16px rgba(0, 107, 46, .12) !important;
}

.progress-page .btn-asu:hover,
.progress-page .btn.btn-asu:hover {
    background: var(--rems-green-dark) !important;
    border-color: var(--rems-green-dark) !important;
}

.progress-page .btn-outline-asu,
.progress-page .btn.btn-outline-asu {
    background: #ffffff !important;
    border-color: rgba(0, 107, 46, .24) !important;
    color: var(--rems-green) !important;
}

.progress-page .btn-outline-asu:hover,
.progress-page .btn.btn-outline-asu:hover {
    background: #f3faf5 !important;
    border-color: var(--rems-green) !important;
    color: var(--rems-green-dark) !important;
}


/* ---------------------------------------------------------
   EMPTY / VIEW-ONLY NOTICE
--------------------------------------------------------- */

.empty-progress {
    padding: 28px 16px !important;

    color: #818b83 !important;
}

.empty-progress i {
    color: #b6c0b8 !important;
}

.design-notice {
    padding: 14px 16px !important;

    border: 1px solid #dfe8e1 !important;
    border-radius: 13px !important;

    background: #fbfdfb !important;
    color: #667169 !important;

    font-size: 10px !important;
    line-height: 1.65 !important;
}


/* ---------------------------------------------------------
   FOOTER
--------------------------------------------------------- */

.content-footer {
    padding-top: 18px !important;
    padding-bottom: 4px !important;

    color: #7a857d !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
}


/* ---------------------------------------------------------
   RESPONSIVE
--------------------------------------------------------- */

@media (max-width: 1050px) {

    .project-info {
        grid-template-columns:
            repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 760px) {

    .page-content {
        padding:
            16px
            13px
            26px !important;
    }

    .progress-heading {
        min-height: 0 !important;

        align-items: flex-start !important;
        flex-direction: column !important;

        padding: 17px !important;
    }

    .progress-heading h1 {
        font-size: 25px !important;
    }

    .progress-heading-actions {
        width: 100% !important;
        justify-content: stretch !important;
    }

    .progress-heading-actions .btn {
        flex: 1 1 auto !important;
    }

    .project-summary-top {
        align-items: flex-start !important;
        flex-direction: column !important;
    }

    .project-info {
        grid-template-columns: 1fr !important;
    }

    .self-final-step {
        grid-template-columns:
            40px
            minmax(0, 1fr) !important;
    }

    .self-final-step-actions {
        grid-column: 1 / -1 !important;
        justify-content: stretch !important;
    }

    .self-final-step-actions .btn {
        width: 100% !important;
    }

    .completed-manuscript-box {
        align-items: stretch !important;
        flex-direction: column !important;
    }

    .completed-manuscript-box .btn,
    .completed-manuscript-actions .btn {
        width: 100% !important;
    }
}



/* =========================================================
   UNIFORM REMS TOP HEADER
   Matches the approved Dashboard/Admin header family.
   UI ONLY — no workflow/business logic changed.
========================================================= */

.rems-uniform-topbar,
.asu-dashboard-topbar {
    /* Keep the Research Progress header visible as the page scrolls. */
    position: sticky !important;
    top: 0 !important;
    z-index: 1030 !important;
    flex-shrink: 0 !important;

    min-height: 76px !important;
    height: 76px !important;

    padding: 0 30px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 20px !important;

    border-bottom: 1px solid rgba(0, 122, 51, .12) !important;

    background: rgba(255, 255, 255, .97) !important;

    box-shadow:
        0 10px 26px rgba(16, 24, 16, .035) !important;
}


.asu-dashboard-brandline {
    display: flex !important;
    align-items: center !important;

    gap: 14px !important;
}


.asu-dashboard-brandline::before {
    content: "" !important;

    width: 3px !important;
    height: 38px !important;

    flex: 0 0 3px !important;

    border-radius: 999px !important;

    background: #007a33 !important;
}


.asu-dashboard-brandline strong {
    display: block !important;

    color: #101810 !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 17px !important;
    font-weight: 800 !important;

    line-height: 1.15 !important;
    letter-spacing: -.015em !important;
}


.asu-dashboard-brandline span {
    display: block !important;

    margin-top: 4px !important;

    color: #657068 !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 13px !important;
    font-weight: 400 !important;

    line-height: 1.15 !important;
}


.asu-dashboard-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;

    gap: 12px !important;
}


.dashboard-user-mini {
    display: flex !important;
    align-items: center !important;

    gap: 12px !important;
}


.dashboard-user-copy {
    text-align: right !important;
}


.dashboard-user-copy strong {
    display: block !important;

    color: #101810 !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;

    line-height: 1.1 !important;
}


.dashboard-user-copy span {
    display: block !important;

    margin-top: 4px !important;

    color: #657068 !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 12px !important;
    font-weight: 400 !important;

    line-height: 1.1 !important;
}


.dashboard-avatar {
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 50% !important;

    background: #ffd51f !important;
    color: #003f1a !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    font-weight: 900 !important;

    box-shadow:
        0 8px 18px rgba(255, 213, 31, .25) !important;
}


@media (max-width: 760px) {

    .rems-uniform-topbar,
    .asu-dashboard-topbar {
        padding: 0 16px !important;
    }

    .asu-dashboard-brandline strong {
        max-width: 240px !important;

        font-size: 14px !important;
        line-height: 1.2 !important;
    }

    .asu-dashboard-brandline span {
        font-size: 11px !important;
    }

    .dashboard-user-copy {
        display: none !important;
    }

    .dashboard-avatar {
        width: 42px !important;
        height: 42px !important;
        min-width: 42px !important;

        font-size: 14px !important;
    }
}



/* =========================================================
   PROJECT MONITORING BANNER — MATCH APPROVED REMS STYLE
   Green banner + yellow action buttons.
========================================================= */

.progress-heading {
    position: relative !important;
    overflow: hidden !important;

    min-height: 96px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;

    padding: 20px 28px !important;
    margin-bottom: 20px !important;

    border: 0 !important;
    border-radius: 18px !important;

    background: #007a33 !important;

    box-shadow:
        0 14px 30px rgba(0, 92, 38, .16) !important;
}

.progress-heading::before {
    content: "" !important;

    position: absolute !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;

    width: 29% !important;

    background:
        linear-gradient(
            110deg,
            rgba(78, 126, 42, .34) 0%,
            rgba(76, 123, 39, .92) 100%
        ) !important;

    clip-path:
        polygon(
            12% 0,
            100% 0,
            100% 100%,
            0 100%
        ) !important;

    pointer-events: none !important;
}

.progress-heading::after {
    display: none !important;
}

.progress-heading > div,
.progress-heading-actions {
    position: relative !important;
    z-index: 2 !important;
}

.progress-heading h1 {
    margin: 0 !important;

    color: #ffffff !important;

    font-size: 30px !important;
    font-weight: 900 !important;
    line-height: 1.15 !important;
}

.progress-heading p {
    margin-top: 5px !important;

    color: rgba(255,255,255,.78) !important;

    font-size: 10.5px !important;
}


/* Yellow buttons inside the green module banner */
.progress-heading-actions .btn,
.progress-heading-actions .btn-outline-asu,
.progress-heading-actions .btn.btn-outline-asu {
    min-height: 40px !important;

    padding: 0 14px !important;

    border: 1px solid #ffd51f !important;
    border-radius: 10px !important;

    background:
        linear-gradient(
            180deg,
            #ffdc36 0%,
            #ffd21f 100%
        ) !important;

    color: #003f1a !important;

    font-size: 10px !important;
    font-weight: 800 !important;

    box-shadow:
        0 8px 18px rgba(255, 210, 31, .24) !important;
}

.progress-heading-actions .btn:hover,
.progress-heading-actions .btn-outline-asu:hover,
.progress-heading-actions .btn.btn-outline-asu:hover {
    background: #f6c900 !important;
    border-color: #f6c900 !important;
    color: #003f1a !important;

    transform: translateY(-1px) !important;
}


@media (max-width: 760px) {

    .progress-heading {
        min-height: 92px !important;

        padding: 18px !important;
    }

    .progress-heading::before {
        width: 38% !important;
        opacity: .78 !important;
    }

    .progress-heading h1 {
        font-size: 25px !important;
    }
}



/* =========================================================
   HEADER BUTTON TEXT FIX
   Yellow button = black text/icon, per approved REMS design.
========================================================= */

.progress-heading-actions .btn,
.progress-heading-actions .btn-outline-asu,
.progress-heading-actions .btn.btn-outline-asu,
.progress-heading-actions .btn i,
.progress-heading-actions .btn-outline-asu i,
.progress-heading-actions .btn.btn-outline-asu i {
    color: #101810 !important;
}

.progress-heading-actions .btn:hover,
.progress-heading-actions .btn-outline-asu:hover,
.progress-heading-actions .btn.btn-outline-asu:hover,
.progress-heading-actions .btn:hover i,
.progress-heading-actions .btn-outline-asu:hover i,
.progress-heading-actions .btn.btn-outline-asu:hover i {
    color: #101810 !important;
}



/* =========================================================
   EXACT REMS COLOR PALETTE SYNC
   Match Research Management page:
   deep green + bright green + yellow + warm green/gold ombre.
   UI ONLY.
========================================================= */

:root {
    --asu-green: #007a33 !important;
    --asu-green-dark: #005c26 !important;
    --asu-green-deep: #003f1a !important;
    --asu-yellow: #ffd51f !important;
    --asu-orange: #ff6a00 !important;
    --asu-black: #101810 !important;
    --asu-muted: #657068 !important;
}


/* Same background family as Research Management */
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

.page-content {
    min-height: calc(100vh - 76px) !important;

    background:
        radial-gradient(
            circle at 90% 5%,
            rgba(255, 213, 31, .10),
            transparent 25%
        ),
        radial-gradient(
            circle at 8% 7%,
            rgba(0, 122, 51, .07),
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


/* Exact main-module green family */
.progress-heading {
    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #2c9a39 100%
        ) !important;

    box-shadow:
        0 18px 42px rgba(0, 90, 40, .18) !important;
}

.progress-heading::before {
    background:
        linear-gradient(
            135deg,
            rgba(255, 213, 31, .11),
            rgba(255, 255, 255, .025)
        ) !important;

    opacity: 1 !important;
}


/* Keep cards truly white like Research Management */
.project-summary,
.progress-panel,
.self-final-workflow,
.progress-update,
.completed-manuscript-box,
.project-info-item,
.self-final-step,
.self-final-file {
    background: #ffffff !important;
}


/* Stronger card border/shadow — no washed-out look */
.project-summary,
.progress-panel,
.self-final-workflow {
    border-color:
        rgba(0, 122, 51, .13) !important;

    box-shadow:
        0 14px 34px
        rgba(16, 24, 16, .055) !important;
}


/* Project summary gets the same clean white + strong accents */
.project-summary h2 {
    color: #101810 !important;
}

.project-meta {
    color: #657068 !important;
}

.project-meta i {
    color: #007a33 !important;
}


/* Completed = yellow accent, same family as Research Management */
.project-status {
    border-color: #ffd51f !important;

    background: #ffd51f !important;
    color: #003f1a !important;

    box-shadow:
        0 8px 18px
        rgba(255, 213, 31, .18) !important;
}


/* Main progress accent is green, label gets ASU yellow emphasis */
.main-progress-head span {
    color: #d29f00 !important;

    font-weight: 700 !important;
}

.main-progress-head strong {
    color: #007a33 !important;
}

.main-progress-track {
    background: #e6eee8 !important;
}

.main-progress-value {
    background:
        linear-gradient(
            90deg,
            #007a33 0%,
            #2c9a39 100%
        ) !important;
}


/* Stronger section icons like Research Management cards */
.progress-panel-icon {
    background:
        rgba(0, 122, 51, .09) !important;

    color: #007a33 !important;
}


/* Self-Initiated status accents */
.self-final-workflow-badge,
.self-final-status {
    border-color:
        rgba(0, 122, 51, .15) !important;

    background:
        rgba(0, 122, 51, .08) !important;

    color: #005c26 !important;
}

.self-final-step-number {
    background:
        rgba(0, 122, 51, .09) !important;

    color: #007a33 !important;
}


/* Yellow header actions — exact ASU yellow */
.progress-heading-actions .btn,
.progress-heading-actions .btn-outline-asu,
.progress-heading-actions .btn.btn-outline-asu {
    border-color: #ffd51f !important;

    background:
        linear-gradient(
            135deg,
            #ffd51f 0%,
            #f6c900 100%
        ) !important;

    color: #101810 !important;

    box-shadow:
        0 10px 20px
        rgba(255, 213, 31, .24) !important;
}

.progress-heading-actions .btn i,
.progress-heading-actions .btn-outline-asu i,
.progress-heading-actions .btn.btn-outline-asu i {
    color: #101810 !important;
}

.progress-heading-actions .btn:hover,
.progress-heading-actions .btn-outline-asu:hover,
.progress-heading-actions .btn.btn-outline-asu:hover {
    background: #f0bd00 !important;
    border-color: #f0bd00 !important;
    color: #101810 !important;
}


/* Stronger primary buttons */
.progress-page .btn-asu,
.progress-page .btn.btn-asu {
    background: #007a33 !important;
    border-color: #007a33 !important;
    color: #ffffff !important;
}

.progress-page .btn-asu:hover,
.progress-page .btn.btn-asu:hover {
    background: #005c26 !important;
    border-color: #005c26 !important;
}


/* Outline controls stay clean, not pale green */
.progress-page .btn-outline-asu,
.progress-page .btn.btn-outline-asu {
    background: #ffffff !important;

    border-color:
        rgba(0, 122, 51, .22) !important;

    color: #007a33 !important;
}


/* Slightly stronger contrast for headings / labels */
.progress-panel-title,
.self-final-step-title h3,
.completed-manuscript-file strong,
.project-info-item strong {
    color: #101810 !important;
}

.project-info-item label,
.form-label,
.progress-count-pill {
    color: #657068 !important;
}


/* Remove pale tinted fills that made Progress look washed out */
.project-info-item,
.completed-manuscript-box,
.self-final-step,
.self-final-file {
    border-color:
        rgba(0, 122, 51, .12) !important;
}


/* Keep topbar exactly in the same family */
.rems-uniform-topbar,
.asu-dashboard-topbar {
    background: rgba(255,255,255,.97) !important;

    border-bottom:
        1px solid rgba(0,122,51,.12) !important;

    box-shadow:
        0 10px 26px
        rgba(16,24,16,.035) !important;
}

.asu-dashboard-brandline::before {
    background: #007a33 !important;
}

.dashboard-avatar {
    background: #ffd51f !important;
    color: #003f1a !important;
}



/* =========================================================
   SIDEBAR
   IMPORTANT: No sidebar colors, widths, active states,
   hover expansion, or collapse behavior are defined here.

   research/progress.php intentionally inherits the shared
   sidebar.php design exactly, so it matches Research Management.
========================================================= */



/* =========================================================
   REGULAR FOR-URRC FINAL PROOF
========================================================= */

.proof-card .progress-panel-header {
    align-items: center !important;
}

.proof-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    min-height: 28px;
    padding: 5px 10px;

    border: 1px solid rgba(0,122,51,.15);
    border-radius: 999px;

    background: rgba(0,122,51,.08);
    color: #005c26;

    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 8.5px;
    font-weight: 700;
}

.proof-status-pill.is-pending {
    border-color: rgba(255,213,31,.72);
    background: #ffd51f;
    color: #101810;
}

.proof-body {
    display: grid;
    gap: 14px;
}

.proof-intro {
    max-width: 900px;
    margin: 0;

    color: #657068;

    font-size: 10px;
    line-height: 1.7;
}

.proof-upload-form {
    display: grid;
    gap: 9px;
}

.proof-upload-row {
    display: grid;
    grid-template-columns: minmax(0,1fr) auto;
    align-items: center;
    gap: 12px;

    padding: 12px;

    border: 1px solid rgba(0,122,51,.13);
    border-radius: 13px;

    background: #fbfdfb;
}

.proof-file-picker {
    min-width: 0;
    overflow: hidden;

    display: flex;
    align-items: center;

    border: 1px solid #d7e0d8;
    border-radius: 10px;

    background: #ffffff;
}

.proof-file-input {
    position: absolute;

    width: 1px;
    height: 1px;

    overflow: hidden;

    clip: rect(0,0,0,0);
    clip-path: inset(50%);
    white-space: nowrap;
}

.proof-file-button {
    min-height: 42px;
    padding: 0 14px;

    display: inline-flex;
    align-items: center;
    gap: 6px;

    margin: 0;

    border-right: 1px solid #d7e0d8;

    background: #edf7ef;
    color: #005c26;

    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 9.5px;
    font-weight: 800;

    cursor: pointer;
}

.proof-file-button:hover {
    background: #e5f3e8;
}

.proof-file-name {
    min-width: 0;
    padding: 0 13px;

    overflow: hidden;

    color: #657068;

    font-size: 9.5px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.proof-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    width: 100%;
    margin-top: 2px;
}

.proof-actions .btn {
    min-width: 150px;
}

.proof-upload-note {
    display: flex;
    align-items: center;
    gap: 6px;

    margin: 0 2px;

    color: #7a857d;

    font-size: 8.5px;
}

.proof-upload-note i {
    color: #007a33;
}

.proof-submitted {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;

    padding: 13px 14px;

    border: 1px solid rgba(0,122,51,.13);
    border-radius: 13px;

    background: #fbfdfb;
}

.proof-submitted-main {
    min-width: 0;

    display: flex;
    align-items: center;
    gap: 11px;
}

.proof-submitted-icon {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;

    background: #edf7ef;
    color: #007a33;

    font-size: 17px;
}

.proof-submitted-copy {
    min-width: 0;
}

.proof-submitted-label {
    display: block;

    margin-bottom: 3px;

    color: #005c26;

    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 8px;
    font-weight: 700;

    text-transform: uppercase;
}

.proof-submitted-copy strong {
    display: block;

    max-width: 600px;

    overflow: hidden;

    color: #101810;

    font-size: 10.5px;
    font-weight: 700;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.proof-submitted-copy small {
    display: block;

    margin-top: 3px;

    color: #758078;

    font-size: 8.5px;
}

.proof-complete-note,
.proof-waiting-note {
    display: flex;
    align-items: center;
    gap: 7px;

    padding: 11px 12px;

    border: 1px solid rgba(0,122,51,.11);
    border-radius: 10px;

    background: #f7fbf8;
    color: #59655c;

    font-size: 9.5px;
}

.proof-complete-note i {
    color: #007a33;
}

.proof-waiting-note i {
    color: #a87d00;
}

@media (max-width: 760px) {

    .proof-upload-row {
        grid-template-columns: 1fr;
    }

    .proof-actions,
    .proof-actions .btn,
    .proof-submitted .btn {
        width: 100%;
    }

    .proof-submitted {
        align-items: stretch;
        flex-direction: column;
    }
}






/* =========================================================
   PROPOSAL DETAILS ONLY
   Exact button style from Research Management > New Proposal
========================================================= */
.progress-heading-actions > .btn.btn-outline-asu.proposal-details-new-proposal-style {
    min-height: 42px !important;
    height: 42px !important;
    padding: 0 14px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border: 0 !important;
    border-radius: 10px !important;

    background: #ffd51f !important;
    color: #003f1a !important;

    font-size: 10.5px !important;
    font-weight: 800 !important;

    box-shadow: 0 8px 18px rgba(255,213,31,.18) !important;
}

.progress-heading-actions > .btn.btn-outline-asu.proposal-details-new-proposal-style i {
    color: #003f1a !important;
}

.progress-heading-actions > .btn.btn-outline-asu.proposal-details-new-proposal-style:hover {
    background: #f0bd00 !important;
    color: #003f1a !important;
}



/* Faculty completion message. Does not change workflow or submission behavior. */
.rems-faculty-congrats {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 20px;
    padding: 19px 22px;
    background: #ffffff;
    border: 1px solid rgba(0,122,51,.22);
    border-left: 5px solid #007a33;
    border-radius: 16px;
    box-shadow: 0 12px 28px rgba(0,79,34,.075);
}
.rems-faculty-congrats-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    border-radius: 13px;
    background: #eaf7ee;
    color: #007a33;
    font-size: 21px;
}
.rems-faculty-congrats h2 {
    margin: 0 0 6px;
    color: #005c26;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.35;
}
.rems-faculty-congrats p {
    margin: 0;
    color: #59695f;
    font-size: 12px;
    line-height: 1.7;
}
.rems-faculty-congrats .completion-date {
    display: inline-block;
    margin-top: 8px;
    color: #007a33;
    font-size: 10px;
    font-weight: 700;
}
@media (max-width: 700px) {
    .rems-faculty-congrats { padding: 16px; gap: 10px; }
    .rems-faculty-congrats h2 { font-size: 16px; }
    .rems-faculty-congrats-icon { width: 38px; height: 38px; flex-basis: 38px; }
}

/* Completed manuscript — require a link preview before the submit button unlocks. */
.completed-manuscript-link-row {
    display: flex;
    align-items: stretch;
    gap: 10px;
    min-width: 0;
}
.completed-manuscript-link-row > .form-control {
    flex: 1 1 auto;
    min-width: 0;
}
.progress-page .completed-manuscript-view-button {
    flex: 0 0 46px;
    width: 46px;
    min-height: 43px;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 10px !important;
    font-size: 18px !important;
}
.progress-page .completed-manuscript-view-button.is-viewed {
    border-color: #007a33 !important;
    background: #edf8f0 !important;
    color: #005c26 !important;
}
.progress-page #completedManuscriptSubmitButton:disabled {
    opacity: .48 !important;
    cursor: not-allowed !important;
    box-shadow: none !important;
}
#completedManuscriptViewMessage.is-viewed {
    color: #007a33 !important;
    font-weight: 700;
}
#completedManuscriptViewMessage.is-error {
    color: #a83b3b !important;
}
/* Five Project Information entries: 3 + 2 instead of an orphaned last card. */
@media (min-width: 1051px) {
    .progress-page .project-info {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
}


/* Terminal Report and Publication/Presentation Proof: same eye + note behavior. */
.progress-page #terminalReportSubmitButton:disabled,
.progress-page #publicationPresentationProofSubmitButton:disabled {
    opacity: .48 !important;
    cursor: not-allowed !important;
    box-shadow: none !important;
}
#terminalReportViewMessage.is-viewed,
#publicationPresentationProofViewMessage.is-viewed {
    color: #007a33 !important;
    font-weight: 700;
}
#terminalReportViewMessage.is-error,
#publicationPresentationProofViewMessage.is-error {
    color: #a83b3b !important;
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

</style>
<?php if (($user['role'] ?? '') === 'rps'): ?>
<style>
/* =========================================================
   RPS PROJECT MONITORING - REMOVE WHITE BOTTOM STRIP
   - Retain the original full-width page and window scrollbar.
   - Prevent scroll chaining / elastic overscroll.
   - Make the page background continue behind any shared-layout gap.
   - Keep the researcher/faculty view completely unchanged.
========================================================= */
html {
    overscroll-behavior-x: none !important;
    overscroll-behavior-y: none !important;
}

body {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
    overscroll-behavior-x: none !important;
    overscroll-behavior-y: none !important;
    background:
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

/* This remains a normal full-width block, NOT a flex or scroll container. */
.main-page {
    height: auto !important;
    min-height: 100vh !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
    overflow: visible !important;
    overscroll-behavior-y: none !important;
}

.main-page > .page-content {
    height: auto !important;
    min-height: calc(100vh - 76px) !important;
    overflow: visible !important;
    overscroll-behavior-y: none !important;
}
</style>
<?php endif; ?>





<style id="rems-research-progress-dark-mode">
/* ============================================================
   ASU-REMS PROJECT PROGRESS — DARK MODE CONTRAST
   View only: the PHP roles, database queries, saved HTTPS
   document links, eye-button confirmation, and upload steps
   remain unmodified. Applies only when the global toggle is dark.
   ============================================================ */

/* Page background and compact shared topbar. The RPS-specific CSS
   appears earlier, so this block must follow it. */
html[data-rems-theme="dark"] body:has(.progress-page),
html[data-rems-theme="dark"] body:has(.progress-page) .main-page,
html[data-rems-theme="dark"] body:has(.progress-page) .main-page > .page-content {
    background: #0b1d15 !important;
    color: #eaf3eb !important;
}
html[data-rems-theme="dark"] .asu-dashboard-topbar.rems-uniform-topbar {
    background: #11291e !important;
    border-bottom-color: #365744 !important;
    box-shadow: 0 7px 24px rgba(0,0,0,.16) !important;
}
html[data-rems-theme="dark"] .asu-dashboard-topbar .asu-dashboard-brandline strong,
html[data-rems-theme="dark"] .asu-dashboard-topbar .dashboard-user-copy strong {
    color: #f1f8f0 !important;
}
html[data-rems-theme="dark"] .asu-dashboard-topbar .asu-dashboard-brandline span,
html[data-rems-theme="dark"] .asu-dashboard-topbar .dashboard-user-copy span {
    color: #bdd0c0 !important;
}

/* Keep the branded heading green, and its action buttons yellow. */
html[data-rems-theme="dark"] .progress-page .progress-heading h1 {
    color: #ffffff !important;
}
html[data-rems-theme="dark"] .progress-page .progress-heading p {
    color: #e4f0e5 !important;
}
html[data-rems-theme="dark"] .progress-page .progress-heading-actions .btn,
html[data-rems-theme="dark"] .progress-page .progress-heading-actions .btn i {
    color: #003f1a !important;
}

/* Main project summary, metadata, and progress percentage. */
html[data-rems-theme="dark"] .progress-page .project-summary {
    background: linear-gradient(145deg,#19392a,#122a1f) !important;
    border-color: #41644e !important;
    box-shadow: 0 12px 28px rgba(0,0,0,.14) !important;
    color: #edf5ed !important;
}
html[data-rems-theme="dark"] .progress-page .project-summary h2 {
    color: #f3faf3 !important;
}
html[data-rems-theme="dark"] .progress-page .project-meta,
html[data-rems-theme="dark"] .progress-page .project-meta span,
html[data-rems-theme="dark"] .progress-page .main-progress-head,
html[data-rems-theme="dark"] .progress-page .main-progress-head small {
    color: #c4d5c7 !important;
}
html[data-rems-theme="dark"] .progress-page .project-meta i {
    color: #8cddb0 !important;
}
html[data-rems-theme="dark"] .progress-page .project-status {
    background: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #10311b !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .progress-page .main-progress {
    border-top-color: #3e614b !important;
}
html[data-rems-theme="dark"] .progress-page .main-progress-head span {
    color: #c5d9c8 !important;
}
html[data-rems-theme="dark"] .progress-page .main-progress-head strong {
    background: transparent !important;
    color: #88e5a9 !important;
}
html[data-rems-theme="dark"] .progress-page .main-progress-track {
    background: #385a45 !important;
}
html[data-rems-theme="dark"] .progress-page .main-progress-value,
html[data-rems-theme="dark"] .progress-page .update-track div {
    background: linear-gradient(90deg,#20a75c,#5bd288) !important;
}

/* Research info, upload panels, and history panels. */
html[data-rems-theme="dark"] .progress-page :is(
    .progress-panel,.self-final-workflow,.progress-update,
    .completed-manuscript-box,.project-info-item,.self-final-step,
    .self-final-file,.uniform-link-upload-card,
    .proof-upload-row,.proof-submitted,.proof-file-picker,
    .design-notice,.rems-faculty-congrats
) {
    background: #152f23 !important;
    color: #eaf3eb !important;
    border-color: #3b5f49 !important;
    box-shadow: 0 9px 24px rgba(0,0,0,.10) !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    .progress-panel-header,.progress-panel-header .progress-panel-title
) {
    background: #1d3c2d !important;
    border-color: #41654e !important;
    color: #f1f7f1 !important;
}
html[data-rems-theme="dark"] .progress-page .progress-panel-body {
    background: #152f23 !important;
    color: #e8f3e8 !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    .progress-panel-title,.project-info-item strong,
    .progress-update h3,.progress-document strong,
    .completed-manuscript-file strong,.uniform-link-upload-title,
    .self-final-step-title h3,.self-final-file-text strong,
    .rems-faculty-congrats h2,.proof-submitted-copy strong,
    .empty-progress strong
) {
    color: #f1f8f1 !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    .project-info-item label,.progress-update-date,
    .update-section strong,.update-section p,
    .completed-manuscript-file small,.progress-document small,
    .form-note,.uniform-link-upload-note,
    .self-final-intro,.self-final-step p,
    .self-final-file-text small,.self-final-complete span,
    .proof-intro,.proof-submitted-copy small,
    .rems-faculty-congrats p,.empty-progress,
    .progress-panel-body .text-muted,.design-notice
) {
    color: #bfd1c1 !important;
}
html[data-rems-theme="dark"] .progress-page .progress-panel-icon,
html[data-rems-theme="dark"] .progress-page :is(
    .uniform-link-upload-icon,.completed-manuscript-file i,
    .self-final-step-number,.proof-submitted-icon,
    .rems-faculty-congrats-icon
) {
    background: #28513a !important;
    color: #9be5b2 !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    .update-section,.progress-update-top,.progress-panel-header
) {
    border-color: #3b5e49 !important;
}
html[data-rems-theme="dark"] .progress-page .progress-history-panel .progress-panel-body::-webkit-scrollbar-thumb {
    background: #446954 !important;
}
html[data-rems-theme="dark"] .progress-page .progress-update:hover {
    border-color: #588364 !important;
}

/* Document and workflow subcards that have their own light fills. */
html[data-rems-theme="dark"] .progress-page :is(
    .progress-document,.self-final-complete,.proof-complete-note,
    .proof-waiting-note,.proof-file-button
) {
    background: #1d3e2d !important;
    border-color: #42654f !important;
    color: #d9eadb !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    .proof-submitted-label,.self-final-complete strong,
    .rems-faculty-congrats .completion-date
) {
    color: #8ce3a8 !important;
}
html[data-rems-theme="dark"] .progress-page .rems-upload-info-note::before {
    color: #8ce3a8 !important;
}
html[data-rems-theme="dark"] .progress-page .proof-file-picker {
    border-color: #4c7056 !important;
}
html[data-rems-theme="dark"] .progress-page .proof-file-button {
    border-right-color: #4c7056 !important;
}
html[data-rems-theme="dark"] .progress-page .proof-file-name,
html[data-rems-theme="dark"] .progress-page .proof-upload-note {
    color: #c0d1c3 !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    .proof-status-pill,.self-final-workflow-badge,
    .self-final-status,.progress-count-pill,.progress-percent,
    .percentage-value
) {
    background: #254a35 !important;
    border-color: #4e7859 !important;
    color: #bcf0cb !important;
}
/* Pending proof remains ASU gold, not green. */
html[data-rems-theme="dark"] .progress-page .proof-status-pill.is-pending {
    background: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #18351e !important;
}
html[data-rems-theme="dark"] .progress-page :is(.update-track) {
    background: #3b6049 !important;
}

/* Form fields and dropdown options retain clear boundaries. */
html[data-rems-theme="dark"] .progress-page :is(
    .form-label,.progress-form .form-label,.proof-upload-form label
) {
    color: #d4e6d5 !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    .form-control,.form-select,.proof-file-picker input,
    .completed-manuscript-form input
) {
    background-color: #203d2e !important;
    border-color: #54715a !important;
    color: #f1f8f0 !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] .progress-page :is(input,textarea)::placeholder {
    color: #b4cbb8 !important;
    opacity: 1;
}
html[data-rems-theme="dark"] .progress-page :is(.form-control,.form-select):focus {
    background-color: #264a36 !important;
    border-color: #83c393 !important;
    box-shadow: 0 0 0 .18rem rgba(131,195,147,.16) !important;
}
html[data-rems-theme="dark"] .progress-page select option {
    background: #203d2e !important;
    color: #f1f8f0 !important;
}
html[data-rems-theme="dark"] .progress-page .form-note.rems-upload-info-note.is-viewed,
html[data-rems-theme="dark"] .progress-page :is(
    #completedManuscriptViewMessage.is-viewed,
    #terminalReportViewMessage.is-viewed,
    #publicationPresentationProofViewMessage.is-viewed
) {
    color: #8be0a3 !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    #completedManuscriptViewMessage.is-error,
    #terminalReportViewMessage.is-error,
    #publicationPresentationProofViewMessage.is-error
) {
    color: #ffaca9 !important;
}

/* Action buttons keep working, including disabled/viewed states. */
html[data-rems-theme="dark"] .progress-page :is(
    .btn-outline-asu,.btn.btn-outline-asu
):not(.proposal-details-new-proposal-style) {
    background: #234331 !important;
    border-color: #5c8565 !important;
    color: #eff7ef !important;
}
html[data-rems-theme="dark"] .progress-page :is(
    .btn-outline-asu,.btn.btn-outline-asu
):not(.proposal-details-new-proposal-style):hover {
    background: #2d583d !important;
    border-color: #81b28b !important;
    color: #ffffff !important;
}
html[data-rems-theme="dark"] .progress-page .completed-manuscript-view-button.is-viewed {
    background: #27543a !important;
    color: #a7f1bc !important;
    border-color: #7bbb8e !important;
}
/* The heading's yellow action is intentional regardless of outline class. */
html[data-rems-theme="dark"] .progress-page .progress-heading-actions :is(.btn,.btn-outline-asu),
html[data-rems-theme="dark"] .progress-page .progress-heading-actions :is(.btn,.btn-outline-asu) i {
    background: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #0f2d17 !important;
}
html[data-rems-theme="dark"] .progress-page .progress-heading-actions :is(.btn,.btn-outline-asu):hover {
    background: #f1c400 !important;
}

/* Keep semantic warning/error banners legible; do not turn red into green. */
html[data-rems-theme="dark"] .progress-page :is(.progress-errors,.alert-danger) {
    background: #47282a !important;
    border-color: #86565a !important;
    color: #ffd9d8 !important;
}
html[data-rems-theme="dark"] .progress-page .alert-warning {
    background: #3c351f !important;
    border-color: #817341 !important;
    color: #ffefb2 !important;
}
html[data-rems-theme="dark"] .progress-page .alert-success {
    background: #224b35 !important;
    border-color: #428260 !important;
    color: #d8f6df !important;
}
html[data-rems-theme="dark"] .progress-page .progress-errors :is(strong,li),
html[data-rems-theme="dark"] .progress-page .alert :is(strong,small) {
    color: inherit !important;
}
html[data-rems-theme="dark"] .progress-page .empty-progress i {
    color: #8bd6a3 !important;
}
</style>

<style id="rems-monitoring-header-nav-buttons">
/* Project Monitoring heading navigation — visual styles only.
   Monitoring List: white/green in light mode, green/white in dark mode.
   Proposal Details: ASU yellow in BOTH modes. */
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn,
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn {
    box-sizing: border-box !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    min-height: 42px !important;
    height: 42px !important;
    padding: 0 14px !important;
    border-radius: 10px !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    text-decoration: none !important;
    white-space: nowrap !important;
    box-shadow: none !important;
    transform: none !important;
    transition: none !important;
}

/* Plain icons: no colored square or badge behind either icon. */
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn > i,
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn > i {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 0 0 auto !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
    background-image: none !important;
    box-shadow: none !important;
    font-size: 13px !important;
    line-height: 1 !important;
}

/* LIGHT MODE: Monitoring List = white with green text + green three-line icon. */
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn {
    background: #ffffff !important;
    background-image: none !important;
    border: 1px solid #d9e9dc !important;
    color: #006b2e !important;
}
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn > i {
    color: #006b2e !important;
}
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn:hover {
    background: #f2faf4 !important;
    border-color: #bbd8c3 !important;
    color: #005c26 !important;
}

/* BOTH MODES: Proposal Details remains the bright yellow button. */
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn,
html[data-rems-theme="dark"] body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn {
    background: #ffd51f !important;
    background-image: none !important;
    border: 1px solid #ffd51f !important;
    color: #003f1a !important;
}
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn > i,
html[data-rems-theme="dark"] body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn > i {
    background: transparent !important;
    color: #003f1a !important;
}
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn:hover,
html[data-rems-theme="dark"] body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn:hover {
    background: #f0bd00 !important;
    border-color: #f0bd00 !important;
    color: #003f1a !important;
}

/* DARK MODE: Monitoring List = deep green + WHITE label + WHITE list icon. */
html[data-rems-theme="dark"] body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn {
    background: #214632 !important;
    background-image: none !important;
    border: 1px solid #638a6d !important;
    color: #ffffff !important;
}
html[data-rems-theme="dark"] body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn > i {
    background: transparent !important;
    color: #ffffff !important;
}
html[data-rems-theme="dark"] body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn:hover {
    background: #2b583e !important;
    border-color: #89a992 !important;
    color: #ffffff !important;
}
html[data-rems-theme="dark"] body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn:hover > i {
    color: #ffffff !important;
}

html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn:focus-visible,
html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn:focus-visible {
    outline: 3px solid #ffd51f !important;
    outline-offset: 3px !important;
}

@media (max-width: 700px) {
    html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-monitoring-nav-btn,
    html body .progress-page .progress-heading .progress-heading-actions > a.btn.rems-proposal-details-nav-btn {
        width: 100% !important;
        min-height: 42px !important;
        height: 42px !important;
    }
}

/* Requested: match Research View's eye-only button dimensions. */
.progress-page .uniform-link-upload-row > button.completed-manuscript-view-button {
    box-sizing: border-box !important;
    flex: 0 0 52px !important;
    flex-basis: 52px !important;
    width: 52px !important;
    min-width: 52px !important;
    max-width: 52px !important;
    height: 48px !important;
    min-height: 48px !important;
    max-height: 48px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.progress-page .uniform-link-upload-row > button.completed-manuscript-view-button > i.bi-eye {
    font-size: 19px !important;
    line-height: 1 !important;
}
</style>


<div class="main-page">


<header class="asu-dashboard-topbar rems-uniform-topbar">

<div class="asu-dashboard-brandline">

<div>

<strong>
Research &amp; Extension Management System
</strong>

<span>
Aklan State University
</span>

</div>

</div>


<div class="asu-dashboard-actions">

<div class="dashboard-user-mini">

<div class="dashboard-user-copy">

<strong>
<?= e(fullName($user)); ?>
</strong>

<span>
<?= e($displayRole); ?>
</span>

</div>


<div class="dashboard-avatar">

<?= e($initials ?: 'U'); ?>

</div>

</div>

</div>

</header>



<main class="page-content">


<div class="progress-page">


<!-- =====================================================
     HEADING
====================================================== -->

<div class="progress-heading">


<div>


<h1>

Project Monitoring

</h1>


<p>

Record and monitor implementation progress after Notice to Proceed has been issued.

</p>


</div>



<div class="progress-heading-actions">


<a
    href="<?= BASE_URL; ?>/research/monitoring.php"
    class="btn btn-outline-asu rems-monitoring-nav-btn"
>

<i class="bi bi-list-check me-1"></i>

Monitoring List

</a>

<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>"
    class="btn btn-outline-asu rems-proposal-details-nav-btn"
>

<i class="bi bi-arrow-left me-1"></i>

Proposal Details

</a>


</div>


</div>



<!-- =====================================================
     FLASH
====================================================== -->

<?php if ($flash): ?>


<div
    class="
        alert
        alert-<?= e(
            $flash['type']
        ); ?>
        alert-dismissible
        fade
        show
    "
>


<?= e(
    $flash['message']
); ?>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>


</div>


<?php endif; ?>



<!-- =====================================================
     ERRORS
====================================================== -->

<?php if ($errors): ?>


<div class="progress-errors" role="alert">


<strong>

Submission unsuccessful. Please check the information or document link provided.

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



<!-- =====================================================
     FACULTY — RESEARCH COMPLETED
     Display only after the final workflow step is saved.
====================================================== -->

<?php if (
    ($user['role'] ?? '') === 'faculty'
    && (int)($research['researcher_id'] ?? 0) === (int)($user['user_id'] ?? 0)
    && ($research['status'] ?? '') === 'Completed'
    && in_array($workflowStatus, ['COMPLETED', 'SELF_INITIATED_COMPLETED'], true)
    && ($isSelfInitiated || $hasPublicationPresentationProofDocument)
): ?>
<section class="rems-faculty-congrats" aria-label="Research completion congratulations">
    <div class="rems-faculty-congrats-icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
    <div>
        <h2>Congratulations! Your Research Is Complete!</h2>
        <p>You have successfully completed all required research steps. Your completed research record and submitted document links are preserved in ASU-REMS for future reference.</p>
        <?php if (!empty($research['completed_at'])): ?>
            <span class="completion-date">Completed on <?= e(date('F d, Y', strtotime($research['completed_at']))); ?></span>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     PROJECT SUMMARY
====================================================== -->

<section class="project-summary">


<div class="project-summary-top">


<div>


<h2>

<?= e(
    $research[
        'research_title'
    ]
); ?>

</h2>


<div class="project-meta">


<span>

<i class="bi bi-person me-1"></i>

<?= e(
    $researcherName
); ?>

</span>


<?php if (
    !empty(
        $research[
            'college_name'
        ]
    )
): ?>


<span>

<i class="bi bi-building me-1"></i>

<?= e(
    $research[
        'college_name'
    ]
); ?>

</span>


<?php endif; ?>


<?php if (
    !empty(
        $research[
            'target_completion_date'
        ]
    )
): ?>


<span>

<i class="bi bi-calendar-check me-1"></i>

Target:

<?= e(
    date(
        'M d, Y',
        strtotime(
            $research[
                'target_completion_date'
            ]
        )
    )
); ?>

</span>


<?php endif; ?>


</div>


</div>



<span class="project-status">

<?= e(
    $research[
        'status'
    ]
); ?>

</span>


</div>



<?php if (
    $research['status']
    === 'Approved'
): ?>


<div class="main-progress">


<div class="main-progress-head">


<div>

<span>

Implementation Status

</span>

<small
    style="
        display:block;
        margin-top:3px;
        color:rgba(255,255,255,.65);
    "
>

The project is awaiting its next required research document.

</small>

</div>


<strong>

Not Started

</strong>


</div>


</div>


<?php else: ?>


<div class="main-progress">


<div class="main-progress-head">


<span>

Overall Research Progress

</span>


<strong>

<?= (int)$research[
    'progress_percentage'
]; ?>%

</strong>


</div>



<div class="main-progress-track">


<div
    class="main-progress-value"
    style="
        width:
        <?= min(
            100,
            max(
                0,
                (int)$research[
                    'progress_percentage'
                ]
            )
        ); ?>%;
    "
></div>


</div>


</div>


<?php endif; ?>


</section>




<?php if ($isSelfInitiatedFinalRecord): ?>

<!-- =====================================================
     SELF-INITIATED RESEARCH WORKFLOW
     ADDITIONAL FINAL RECORD SECTION
====================================================== -->

<section class="progress-panel self-final-workflow">


<div class="progress-panel-header">


<h2 class="progress-panel-title">

<span class="progress-panel-icon">
<i class="bi bi-diagram-3"></i>
</span>

Self-Initiated Research Workflow

</h2>


<span class="self-final-workflow-badge">

<i class="bi bi-check-circle-fill"></i>

Completed

</span>


</div>


<div class="progress-panel-body">



<div class="self-final-steps">


<!-- STEP 1 -->
<div class="self-final-step">


<div class="self-final-step-number">
01
</div>


<div class="self-final-step-content">


<div class="self-final-step-title">

<h3>
University Pool of Experts (UPE) Certificates
</h3>

<span class="self-final-status">
<i class="bi bi-check-circle-fill"></i>
Completed
</span>

</div>


<p>
The two required UPE certificates for the Self-Initiated pathway.
</p>


<div class="self-final-files">


<?php if ($upeCertificate1Document): ?>

<div class="self-final-file">

<i class="bi bi-file-earmark-check-fill self-final-file-icon"></i>

<div class="self-final-file-text">

<strong>
<?= e($upeCertificate1Document['original_name']); ?>
</strong>

<small>
Certificate 1 ·
<?= e(progressFileSize((int)$upeCertificate1Document['file_size'])); ?>
</small>

</div>

</div>

<?php endif; ?>


<?php if ($upeCertificate2Document): ?>

<div class="self-final-file">

<i class="bi bi-file-earmark-check-fill self-final-file-icon"></i>

<div class="self-final-file-text">

<strong>
<?= e($upeCertificate2Document['original_name']); ?>
</strong>

<small>
Certificate 2 ·
<?= e(progressFileSize((int)$upeCertificate2Document['file_size'])); ?>
</small>

</div>

</div>

<?php endif; ?>


</div>


</div>


<div class="self-final-step-actions">


<?php if ($upeCertificate1Document): ?>

<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>&document_id=<?= (int)$upeCertificate1Document['document_id']; ?>&file_mode=view"
    class="btn btn-outline-asu btn-sm"
>
View Cert. 1
</a>

<?php endif; ?>


<?php if ($upeCertificate2Document): ?>

<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>&document_id=<?= (int)$upeCertificate2Document['document_id']; ?>&file_mode=view"
    class="btn btn-outline-asu btn-sm"
>
View Cert. 2
</a>

<?php endif; ?>


</div>


</div>


<!-- STEP 2 -->
<div class="self-final-step">


<div class="self-final-step-number">
02
</div>


<div class="self-final-step-content">


<div class="self-final-step-title">

<h3>
Permit to Publish / Present
</h3>

<span class="self-final-status">
<i class="bi bi-check-circle-fill"></i>
Generated
</span>

</div>


<p>
The system-generated permit is part of the completed Self-Initiated record.
</p>


<div class="self-final-files">


<div class="self-final-file">

<i class="bi bi-patch-check-fill self-final-file-icon"></i>

<div class="self-final-file-text">

<strong>
Permit to Publish / Present
</strong>

<small>

Permit No.
<?= e($selfInitiatedPermitNumber); ?>

</small>

</div>

</div>


</div>


</div>


<div class="self-final-step-actions">


<a
    href="<?= e($selfInitiatedPermitUrl); ?>"
    class="btn btn-outline-asu btn-sm"
>
View Permit
</a>


</div>


</div>


<!-- STEP 3 -->
<div class="self-final-step">


<div class="self-final-step-number">
03
</div>


<div class="self-final-step-content">


<div class="self-final-step-title">

<h3>
Final Publication File
</h3>

<span class="self-final-status">
<i class="bi bi-check-circle-fill"></i>
Completed
</span>

</div>


<?php if ($selfInitiatedPublicationDocument): ?>

<p>
The final publication file completed the Self-Initiated research workflow.
</p>


<div class="self-final-files">


<div class="self-final-file">

<i class="bi bi-file-earmark-check-fill self-final-file-icon"></i>

<div class="self-final-file-text">

<strong>
<?= e($selfInitiatedPublicationDocument['original_name']); ?>
</strong>

<small>

<?= e(strtoupper((string)$selfInitiatedPublicationDocument['file_extension'])); ?>

·

<?= e(progressFileSize((int)$selfInitiatedPublicationDocument['file_size'])); ?>

· Uploaded

<?= e(date('M d, Y', strtotime($selfInitiatedPublicationDocument['uploaded_at']))); ?>

</small>

</div>

</div>


</div>

<?php else: ?>

<p>
No final publication file was found in the research record.
</p>

<?php endif; ?>


</div>


<div class="self-final-step-actions">


<?php if ($selfInitiatedPublicationDocument): ?>

<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>&document_id=<?= (int)$selfInitiatedPublicationDocument['document_id']; ?>&file_mode=view"
    class="btn btn-outline-asu btn-sm"
>
View Publication
</a>

<?php endif; ?>


</div>


</div>


</div>





</div>


</section>


<?php endif; ?>




<!-- =====================================================
     GRID
====================================================== -->

<div class="progress-grid">


<!-- RESEARCH INFORMATION AND REQUIRED DOCUMENTS -->

<div class="progress-column">


<!-- =====================================================
     PROJECT INFO
====================================================== -->

<section class="progress-panel">


<div class="progress-panel-header">


<h2 class="progress-panel-title">


<span class="progress-panel-icon">

<i class="bi bi-info-circle"></i>

</span>

Project Information

</h2>


</div>



<div class="progress-panel-body">


<div class="project-info">


<div class="project-info-item">


<label>

Researcher

</label>


<strong>

<?= e(
    $researcherName
); ?>

</strong>


</div>



<div class="project-info-item">


<label>

College

</label>


<strong>

<?= e(
    $research[
        'college_name'
    ]
    ?: 'Not assigned'
); ?>

</strong>


</div>



<div class="project-info-item">


<label>

Department

</label>


<strong>

<?= e(
    $research[
        'department_name'
    ]
    ?: 'Not assigned'
); ?>

</strong>


</div>



<div class="project-info-item">


<label>

Proposed Start Date

</label>


<strong>

<?php if (
    !empty(
        $research[
            'start_date'
        ]
    )
): ?>

<?= e(
    date(
        'F d, Y',
        strtotime(
            $research[
                'start_date'
            ]
        )
    )
); ?>

<?php else: ?>

Not specified

<?php endif; ?>

</strong>


</div>



<div class="project-info-item">


<label>

Target Completion

</label>


<strong>

<?php if (
    !empty(
        $research[
            'target_completion_date'
        ]
    )
): ?>

<?= e(
    date(
        'F d, Y',
        strtotime(
            $research[
                'target_completion_date'
            ]
        )
    )
); ?>

<?php else: ?>

Not specified

<?php endif; ?>

</strong>


</div>


</div>


</div>


</section>



<!-- =====================================================
     COMPLETED RESEARCH MANUSCRIPT
====================================================== -->

<section class="progress-panel completed-manuscript-card">

<div class="progress-panel-header">

<h2 class="progress-panel-title">

<span class="progress-panel-icon">
<i class="bi bi-file-earmark-check"></i>
</span>

Completed Research Manuscript

</h2>

</div>

<div class="progress-panel-body completed-manuscript-body">

<?php if ($hasCompletedManuscriptDocument): ?>

<div class="completed-manuscript-box">

<div class="completed-manuscript-file">

<i class="bi bi-file-earmark-text"></i>

<div>

<strong>
<?= e($completedManuscriptDocument['original_name']); ?>
</strong>

<small>
<?= in_array(strtolower((string)$completedManuscriptDocument['file_extension']), ['url', 'link'], true) ? 'External link' : e(strtoupper((string)$completedManuscriptDocument['file_extension']) . ' · ' . progressFileSize((int)$completedManuscriptDocument['file_size'])); ?>
· Uploaded
<?= e(date('M d, Y', strtotime($completedManuscriptDocument['uploaded_at']))); ?>
</small>

</div>

</div>

<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>&document_id=<?= (int)$completedManuscriptDocument['document_id']; ?>&file_mode=view"
    class="btn btn-outline-asu btn-sm"
>
<i class="bi bi-eye me-1" aria-hidden="true"></i> View Manuscript
</a>

</div>

<?php elseif ($canUploadCompletedManuscript): ?>

<form
    method="POST"
    class="completed-manuscript-form"
    id="completedManuscriptForm"
>

<input
    type="hidden"
    name="action"
    value="upload_completed_manuscript"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>

<div class="uniform-link-upload-card">

<div class="uniform-link-upload-head">
    <span class="uniform-link-upload-icon">
        <i class="bi bi-file-earmark-text"></i>
    </span>
    <h3 class="uniform-link-upload-title">
        Completed Research Manuscript Link <span class="uniform-link-upload-required">*</span>
    </h3>
</div>

<div class="uniform-link-upload-fields">
    <div class="uniform-link-upload-row">
        <input
            type="url"
            id="completedManuscriptLink"
            name="completed_manuscript_link"
            class="form-control"
            placeholder="https://drive.google.com/file/d/..."
            maxlength="255"
            inputmode="url"
            required
        >
        <button
            type="button"
            class="btn btn-outline-asu completed-manuscript-view-button"
            id="completedManuscriptViewButton"
            aria-label="View completed manuscript link"
            title="View link in a new tab"
        >
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
    </div>

    <div class="form-note rems-upload-info-note uniform-link-upload-note" id="completedManuscriptViewMessage" role="status" aria-live="polite">
        You must enter a shareable HTTPS document link and click the eye button to view it before submitting.
    </div>
</div>

</div>

<div class="completed-manuscript-actions">

<button
    type="submit"
    class="btn btn-asu"
    id="completedManuscriptSubmitButton"
    disabled
    title="View the completed manuscript link first"
>
<i class="bi bi-upload me-1"></i>
<span id="completedManuscriptSubmitText">Submit Completed Manuscript</span>
</button>

</div>

</form>

<?php else: ?>

<div class="completed-manuscript-box">

<div class="completed-manuscript-file">

<i class="bi bi-file-earmark"></i>

<div>

<strong>
No completed manuscript submitted yet.
</strong>

<small>
This section will update once the faculty researcher submits the completed research manuscript.
</small>

</div>

</div>

</div>

<?php endif; ?>

</div>

</section>



<!-- =====================================================
     TERMINAL REPORT
====================================================== -->

<?php if (
    $workflowStatus === 'FOR_TERMINAL_REPORT'
    ||
    $hasTerminalReportDocument
    ||
    in_array(
        $workflowStatus,
        [
            'TERMINAL_REPORT_SUBMITTED',
            'PERMIT_ISSUED',
            'COMPLETED'
        ],
        true
    )
): ?>

<section class="progress-panel completed-manuscript-card">

<div class="progress-panel-header">

<h2 class="progress-panel-title">

<span class="progress-panel-icon">
<i class="bi bi-file-earmark-bar-graph"></i>
</span>

Terminal Report

</h2>

</div>

<div class="progress-panel-body completed-manuscript-body">

<?php if ($hasTerminalReportDocument): ?>

<div class="completed-manuscript-box">

<div class="completed-manuscript-file">

<i class="bi bi-file-earmark-text"></i>

<div>

<strong>
<?= e($terminalReportDocument['original_name']); ?>
</strong>

<small>
<?= in_array(strtolower((string)$terminalReportDocument['file_extension']), ['url', 'link'], true) ? 'External link' : e(strtoupper((string)$terminalReportDocument['file_extension']) . ' · ' . progressFileSize((int)$terminalReportDocument['file_size'])); ?>
· Uploaded
<?= e(date('M d, Y', strtotime($terminalReportDocument['uploaded_at']))); ?>
</small>

</div>

</div>

<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>&document_id=<?= (int)$terminalReportDocument['document_id']; ?>&file_mode=view"
    class="btn btn-outline-asu btn-sm"
>
<i class="bi bi-eye me-1" aria-hidden="true"></i> View Terminal Report
</a>

</div>

<?php elseif ($canUploadTerminalReport): ?>

<form
    method="POST"
    class="completed-manuscript-form"
    id="terminalReportForm"
>

<input
    type="hidden"
    name="action"
    value="upload_terminal_report"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>

<div class="uniform-link-upload-card">

<div class="uniform-link-upload-head">
    <span class="uniform-link-upload-icon">
        <i class="bi bi-file-earmark-bar-graph"></i>
    </span>
    <h3 class="uniform-link-upload-title">
        Terminal Report Link <span class="uniform-link-upload-required">*</span>
    </h3>
</div>

<div class="uniform-link-upload-fields">
    <div class="uniform-link-upload-row">
        <input
            type="url"
            id="terminalReportLink"
            name="terminal_report_link"
            class="form-control"
            placeholder="https://drive.google.com/file/d/..."
            maxlength="255"
            inputmode="url"
            required
        >
        <button
            type="button"
            class="btn btn-outline-asu completed-manuscript-view-button"
            id="terminalReportViewButton"
            aria-label="View terminal report link"
            title="View link in a new tab"
        ><i class="bi bi-eye" aria-hidden="true"></i></button>
    </div>

    <div class="form-note rems-upload-info-note uniform-link-upload-note" id="terminalReportViewMessage" role="status" aria-live="polite">
        You must enter a shareable HTTPS document link and click the eye button to view it before submitting.
    </div>
</div>

</div>

<div class="completed-manuscript-actions">

<button
    type="submit"
    class="btn btn-asu"
    id="terminalReportSubmitButton"
    disabled
    title="View the Terminal Report link first"
>
<i class="bi bi-upload me-1"></i>
<span id="terminalReportSubmitText">Submit Terminal Report</span>
</button>

</div>

</form>

<?php else: ?>

<div class="completed-manuscript-box">

<div class="completed-manuscript-file">

<i class="bi bi-hourglass-split"></i>

<div>

<strong>
Terminal report is waiting for the assigned faculty researcher.
</strong>

<small>
Once submitted, the report will move to the RPS permit-processing queue.
</small>

</div>

</div>

</div>

<?php endif; ?>

</div>

</section>

<?php endif; ?>



<!-- =====================================================
     PROOF OF PUBLICATION / PRESENTATION
     FINAL STEP — REGULAR FOR-URRC PATH
====================================================== -->

<?php if (
    !$isSelfInitiated
    &&
    (
        $workflowStatus === 'PERMIT_ISSUED'
        ||
        $hasPublicationPresentationProofDocument
    )
): ?>

<section class="progress-panel proof-card">


<div class="progress-panel-header">


<h2 class="progress-panel-title">


<span class="progress-panel-icon">
<i class="bi bi-patch-check"></i>
</span>


Proof of <?= e($permitProofTypeLabel); ?>


</h2>


<?php if (
    $hasPublicationPresentationProofDocument
): ?>

<span class="proof-status-pill">
<i class="bi bi-check-circle-fill"></i>
Completed
</span>

<?php elseif (
    $canUploadPublicationPresentationProof
): ?>

<span class="proof-status-pill is-pending">
Final Step
</span>

<?php endif; ?>


</div>


<div class="progress-panel-body proof-body">


<?php if (
    $hasPublicationPresentationProofDocument
): ?>


<div class="proof-submitted">


<div class="proof-submitted-main">


<span class="proof-submitted-icon">

<i class="bi bi-file-earmark-check"></i>

</span>


<div class="proof-submitted-copy">


<span class="proof-submitted-label">

Proof of <?= e($permitProofTypeLabel); ?>
Submitted

</span>


<strong>

<?= e(
    $publicationPresentationProofDocument[
        'original_name'
    ]
); ?>

</strong>


<small>

<?= in_array(strtolower((string)$publicationPresentationProofDocument['file_extension']), ['url', 'link'], true)
    ? 'External link'
    : e(strtoupper((string)$publicationPresentationProofDocument['file_extension']) . ' · '
        . progressFileSize((int)$publicationPresentationProofDocument['file_size'])); ?>

· Uploaded

<?= e(
    date(
        'M d, Y',
        strtotime(
            $publicationPresentationProofDocument[
                'uploaded_at'
            ]
        )
    )
); ?>

</small>


</div>


</div>


<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>&document_id=<?= (int)$publicationPresentationProofDocument['document_id']; ?>&file_mode=view&from=project_monitoring"
    class="btn btn-outline-asu"
>

<i class="bi bi-eye me-1"></i>

View Proof

</a>


</div>


<?php if (
    $workflowStatus === 'COMPLETED'
): ?>


<div class="proof-complete-note">

<i class="bi bi-check-circle-fill"></i>

<span>
All regular research workflow requirements have been completed.
</span>

</div>


<?php endif; ?>


<?php elseif (
    $canUploadPublicationPresentationProof
): ?>


<form
    method="POST"
    class="proof-upload-form"
    id="publicationPresentationProofForm"
>


<input
    type="hidden"
    name="action"
    value="upload_publication_presentation_proof"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>

<div class="uniform-link-upload-card">

<div class="uniform-link-upload-head">
    <span class="uniform-link-upload-icon">
        <i class="bi bi-patch-check"></i>
    </span>
    <h3 class="uniform-link-upload-title">
        Proof of <?= e($permitProofTypeLabel); ?> Link <span class="uniform-link-upload-required">*</span>
    </h3>
</div>

<div class="uniform-link-upload-fields">
    <div class="uniform-link-upload-row">
        <input
            type="url"
            id="publicationPresentationProofLink"
            name="publication_presentation_proof_link"
            class="form-control"
            placeholder="https://drive.google.com/file/d/..."
            maxlength="255"
            inputmode="url"
            aria-label="Proof of publication or presentation HTTPS link"
            required
        >
        <button
            type="button"
            class="btn btn-outline-asu completed-manuscript-view-button"
            id="publicationPresentationProofViewButton"
            aria-label="View proof of publication or presentation link"
            title="View link in a new tab"
        ><i class="bi bi-eye" aria-hidden="true"></i></button>
    </div>

    <div class="form-note rems-upload-info-note uniform-link-upload-note" id="publicationPresentationProofViewMessage" role="status" aria-live="polite">
        You must enter a shareable HTTPS document link and click the eye button to view it before submitting.
    </div>
</div>

</div>

<div class="proof-actions">

<button
    type="submit"
    class="btn btn-asu"
    id="publicationPresentationProofSubmitButton"
    disabled
    title="View the proof link first"
>

<i class="bi bi-cloud-arrow-up me-1"></i>

<span
    id="publicationPresentationProofSubmitText"
>

Submit Proof

</span>


</button>


</div>


</form>


<?php else: ?>


<div class="proof-waiting-note">

<i class="bi bi-hourglass-split"></i>

<span>
Proof link submission becomes available after RPS issues the publication or presentation permit.
</span>

</div>


<?php endif; ?>


</div>


</section>


<?php endif; ?>


</div>


</div>




<footer class="content-footer">


<span>

© <?= date('Y'); ?>
Aklan State University

</span>


<span class="footer-divider">

•

</span>


<span>

Research & Extension Management System

</span>


</footer>


</div>


</main>


</div>




<script>
/* View-before-submit for the other two Project Monitoring document links. */
document.addEventListener('DOMContentLoaded', function () {
    function bindLinkViewBeforeSubmit(options) {
        const form = document.getElementById(options.formId);
        const input = document.getElementById(options.inputId);
        const eye = document.getElementById(options.eyeId);
        const submit = document.getElementById(options.submitId);
        const note = document.getElementById(options.noteId);
        if (!form || !input || !eye || !submit || !note) return;

        let openedUrl = '';
        const instruction = note.textContent.trim();
        function reset() {
            openedUrl = '';
            submit.disabled = true;
            submit.title = 'View the document link first';
            eye.classList.remove('is-viewed');
            note.classList.remove('is-viewed', 'is-error');
            note.textContent = instruction;
        }
        reset();
        input.addEventListener('input', reset);
        eye.addEventListener('click', async function () {
            const value = input.value.trim();
            let url;
            try { url = new URL(value); } catch (error) { url = null; }
            if (!url || url.protocol !== 'https:' || !url.hostname ||
                url.username || url.password || value.length > 255 ||
                url.hostname.toLowerCase() === 'localhost' ||
                url.hostname.toLowerCase().endsWith('.local')) {
                reset();
                note.textContent = 'Please enter a valid HTTPS document link first.';
                note.classList.add('is-error');
                input.focus();
                return;
            }
            // Only a deliberate click opens the link in a new tab.
            const previewTab = window.open('about:blank', '_blank');
            if (!previewTab) {
                reset();
                note.textContent = 'Please allow pop-ups and click the eye button again.';
                note.classList.add('is-error');
                return;
            }
            previewTab.opener = null;
            previewTab.location.replace(value);

            submit.disabled = true;
            eye.disabled = true;
            note.classList.remove('is-error');
            note.textContent = 'Opening link and recording your view...';
            try {
                const csrfInput = form.querySelector('input[name="csrf_token"]');
                const request = new FormData();
                request.append('action', 'confirm_progress_document_link_view');
                request.append('view_kind', options.kind);
                request.append(options.field, value);
                request.append('csrf_token', csrfInput ? csrfInput.value : '');
                const response = await fetch(window.location.href, {
                    method: 'POST', body: request, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }, cache: 'no-store'
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to confirm the link visit.');
                }
                if (input.value.trim() === value) {
                    openedUrl = value;
                    submit.disabled = false;
                    submit.title = '';
                    eye.classList.add('is-viewed');
                    note.classList.add('is-viewed');
                    note.textContent = 'Link opened. You may now submit this document.';
                }
            } catch (error) {
                reset();
                note.classList.add('is-error');
                note.textContent = error.message || 'Please click the eye button again.';
            } finally {
                eye.disabled = false;
            }
        });

        form.addEventListener('submit', function (event) {
            if (!openedUrl || openedUrl !== input.value.trim()) {
                event.preventDefault();
                submit.disabled = true;
                return;
            }
        }, true);
    }
    bindLinkViewBeforeSubmit({
        formId: 'terminalReportForm', inputId: 'terminalReportLink',
        eyeId: 'terminalReportViewButton', noteId: 'terminalReportViewMessage',
        submitId: 'terminalReportSubmitButton', kind: 'terminal_report', field: 'terminal_report_link'
    });
    bindLinkViewBeforeSubmit({
        formId: 'publicationPresentationProofForm', inputId: 'publicationPresentationProofLink',
        eyeId: 'publicationPresentationProofViewButton', noteId: 'publicationPresentationProofViewMessage',
        submitId: 'publicationPresentationProofSubmitButton', kind: 'publication_presentation_proof',
        field: 'publication_presentation_proof_link'
    });
});
</script>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {
        const completedForm =
            document.getElementById(
                'completedManuscriptForm'
            );

        const completedSubmitButton =
            document.getElementById(
                'completedManuscriptSubmitButton'
            );

        const completedSubmitText =
            document.getElementById(
                'completedManuscriptSubmitText'
            );

        if (completedForm && completedSubmitButton) {
            const manuscriptLinkInput = document.getElementById('completedManuscriptLink');
            const manuscriptViewButton = document.getElementById('completedManuscriptViewButton');
            const manuscriptViewMessage = document.getElementById('completedManuscriptViewMessage');
            let confirmedLink = '';

            function resetManuscriptView() {
                confirmedLink = '';
                completedSubmitButton.disabled = true;
                completedSubmitButton.dataset.viewConfirmed = 'no';
                completedSubmitButton.title = 'View the completed manuscript link first';
                manuscriptViewButton.classList.remove('is-viewed');
                manuscriptViewMessage.classList.remove('is-viewed', 'is-error');
                manuscriptViewMessage.textContent =
                    'You must enter a shareable HTTPS document link and click the eye button to view it before submitting.';
            }

            if (manuscriptLinkInput && manuscriptViewButton && manuscriptViewMessage) {
                resetManuscriptView();
                manuscriptLinkInput.addEventListener('input', resetManuscriptView);

                manuscriptViewButton.addEventListener('click', async function () {
                    const link = manuscriptLinkInput.value.trim();
                    let parsedLink;
                    try {
                        parsedLink = new URL(link);
                    } catch (e) {
                        parsedLink = null;
                    }

                    if (!parsedLink || parsedLink.protocol !== 'https:' || link.length > 255) {
                        resetManuscriptView();
                        manuscriptViewMessage.textContent = 'Please enter a valid HTTPS document link first.';
                        manuscriptViewMessage.classList.add('is-error');
                        manuscriptLinkInput.focus();
                        return;
                    }

                    // Opening the real link requires this explicit user click.
                    // Use a new tab so the researcher does not lose the form.
                    const previewTab = window.open('about:blank', '_blank');
                    if (!previewTab) {
                        resetManuscriptView();
                        manuscriptViewMessage.textContent =
                            'Your browser blocked the new tab. Allow pop-ups and click the eye button again.';
                        manuscriptViewMessage.classList.add('is-error');
                        return;
                    }
                    previewTab.opener = null;
                    previewTab.location.replace(link);

                    completedSubmitButton.disabled = true;
                    manuscriptViewButton.disabled = true;
                    manuscriptViewMessage.classList.remove('is-error');
                    manuscriptViewMessage.textContent = 'Opening link and confirming view...';

                    try {
                        const csrf = completedForm.querySelector('input[name="csrf_token"]');
                        const payload = new FormData();
                        payload.append('action', 'confirm_completed_manuscript_link_view');
                        payload.append('completed_manuscript_link', link);
                        payload.append('csrf_token', csrf ? csrf.value : '');

                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: payload,
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store'
                        });
                        const result = await response.json();
                        if (!response.ok || !result.success) {
                            throw new Error(result.message || 'Unable to confirm the document link.');
                        }

                        // Do not enable the button if the URL was edited during the request.
                        if (manuscriptLinkInput.value.trim() === link) {
                            confirmedLink = link;
                            completedSubmitButton.dataset.viewConfirmed = 'yes';
                            completedSubmitButton.disabled = false;
                            completedSubmitButton.title = 'Submit completed manuscript';
                            manuscriptViewButton.classList.add('is-viewed');
                            manuscriptViewMessage.classList.add('is-viewed');
                            manuscriptViewMessage.textContent =
                                'Link opened successfully. You may now submit the completed manuscript.';
                        }
                    } catch (error) {
                        resetManuscriptView();
                        manuscriptViewMessage.classList.add('is-error');
                        manuscriptViewMessage.textContent = error.message ||
                            'Unable to verify the link. Please try clicking the eye button again.';
                    } finally {
                        manuscriptViewButton.disabled = false;
                    }
                });
            }

            completedForm.addEventListener('submit', function (event) {
                if (completedSubmitButton.dataset.viewConfirmed !== 'yes'
                    || confirmedLink !== (manuscriptLinkInput ? manuscriptLinkInput.value.trim() : '')) {
                    event.preventDefault();
                    completedSubmitButton.disabled = true;
                    return;
                }

                completedSubmitButton.disabled = true;
                if (completedSubmitText) {
                    completedSubmitText.textContent = 'Submitting...';
                }
            });
        }

        const terminalForm =
            document.getElementById(
                'terminalReportForm'
            );

        const terminalSubmitButton =
            document.getElementById(
                'terminalReportSubmitButton'
            );

        const terminalSubmitText =
            document.getElementById(
                'terminalReportSubmitText'
            );

        if (
            terminalForm
            &&
            terminalSubmitButton
        ) {

            terminalForm.addEventListener(
                'submit',
                function () {

                    terminalSubmitButton.disabled = true;

                    if (terminalSubmitText) {
                        terminalSubmitText.textContent =
                            'Submitting...';
                    }

                }
            );

        }

    }
);

</script>



<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {
        const proofForm =
            document.getElementById(
                'publicationPresentationProofForm'
            );

        const proofSubmitButton =
            document.getElementById(
                'publicationPresentationProofSubmitButton'
            );

        const proofSubmitText =
            document.getElementById(
                'publicationPresentationProofSubmitText'
            );

        if (
            proofForm
            &&
            proofSubmitButton
        ) {

            proofForm.addEventListener(
                'submit',
                function () {

                    proofSubmitButton.disabled =
                        true;

                    if (
                        proofSubmitText
                    ) {

                        proofSubmitText.textContent =
                            'Submitting...';
                    }
                }
            );
        }
    }
);

</script>


<?php

require __DIR__ .
    '/../includes/footer.php';

?>