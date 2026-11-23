<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/includes/extension_workflow.php';

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
            'extensionist',
            'extension_coordinator',
            'extension_service_office'
        ],
        true
    )
) {

    redirect(
        BASE_URL . '/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$user = currentUser();

$pageTitle = 'Extension Program/Project Details';
$activePage = 'extension';

$errors = [];


/*
|--------------------------------------------------------------------------
| EXTENSION ID
|--------------------------------------------------------------------------
*/

$extensionId =
    isset($_GET['id'])
        ? (int)$_GET['id']
        : 0;


if ($extensionId <= 0) {

    setFlash(
        'danger',
        'Invalid extension program/project record.'
    );

    redirect(
        BASE_URL . '/extension/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD EXTENSION PROGRAM
|--------------------------------------------------------------------------
*/

function loadExtensionProgram(
    PDO $pdo,
    int $extensionId
): array|false {

    $stmt = $pdo->prepare("
        SELECT

            ep.extension_id,
            ep.coordinator_id,
            ep.call_id,

            ep.program_title,
            ep.program_category,
            ep.program_type,
            ep.campus,

            ep.target_community,
            ep.municipality,
            ep.province,

            ep.objectives,
            ep.program_description,
            ep.expected_output,

            ep.funding_source,
            ep.estimated_budget,
            ep.target_participants,

            ep.start_date,
            ep.end_date,

            ep.status,
            ep.progress_percentage,

            ep.submitted_at,
            ep.approved_at,
            ep.completed_at,

            ep.created_at,
            ep.updated_at,

            u.first_name,
            u.middle_name,
            u.last_name,
            u.email,

            c.college_name,
            d.department_name

        FROM extension_programs ep

        INNER JOIN users u
            ON u.user_id = ep.coordinator_id

        LEFT JOIN colleges c
            ON c.college_id = ep.college_id

        LEFT JOIN departments d
            ON d.department_id = ep.department_id

        WHERE ep.extension_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $extensionId
    ]);

    return $stmt->fetch(
        PDO::FETCH_ASSOC
    );
}


$program =
    loadExtensionProgram(
        $pdo,
        $extensionId
    );


if (!$program) {

    setFlash(
        'danger',
        'Extension program/project not found.'
    );

    redirect(
        BASE_URL . '/extension/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| RECORD ACCESS
|--------------------------------------------------------------------------
*/

if (
    extensionIsProponent()
    &&
    (int)$program['coordinator_id']
    !==
    (int)$user['user_id']
) {

    setFlash(
        'danger',
        'You are not authorized to view this extension proposal.'
    );

    redirect(
        BASE_URL . '/extension/index.php'
    );
}


if (
    $user['role'] === 'admin'
    &&
    $program['status'] === 'Draft'
) {

    setFlash(
        'danger',
        'Draft extension proposals are private to their proponents.'
    );

    redirect(
        BASE_URL . '/admin/records/extension.php'
    );
}


/*
|--------------------------------------------------------------------------
| ESO MAY VIEW SUBMITTED PROPOSALS, NOT PRIVATE DRAFTS
|--------------------------------------------------------------------------
*/
if (extensionIsOffice() && $program['status'] === 'Draft') {
    setFlash('danger', 'Draft extension proposals are private to their proponents.');
    redirect(BASE_URL . '/extension/index.php');
}

/*
|--------------------------------------------------------------------------
| ADMINISTRATOR VIEW-ONLY ENFORCEMENT
|--------------------------------------------------------------------------
*/
if (
    ($user['role'] ?? '') === 'admin'
    && !isset($_GET['document_id'])
) {
    redirect(
        BASE_URL . '/admin/records/extension_view.php?id=' . $extensionId
    );
}


/*
|--------------------------------------------------------------------------
| FINAL OFFICIAL NOTICE & BOR CERTIFICATION (READ-ONLY)
|--------------------------------------------------------------------------
| Accessible to the authorized owner/ESO through the existing record access.
| Never change proposal status from this viewer.
*/
$issuedNotice = null;
$officialCertification = null;
$officialBorNotation = null;

if (in_array((string) $program['status'], ['Approved', 'Ongoing', 'Completed'], true)) {
    try {
        // This table is introduced by extension_notice_of_approval.sql.
        $tableExists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extension_notices'"
        );
        if ((int) $tableExists->fetchColumn() > 0) {
            $stmtNotice = $pdo->prepare(
                'SELECT notice_reference, notice_date, notice_url, remarks, issued_at
                 FROM extension_notices WHERE extension_id = ? LIMIT 1'
            );
            $stmtNotice->execute([$extensionId]);
            $issuedNotice = $stmtNotice->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $stmtCert = $pdo->prepare(
            'SELECT document_type, reference_number, certification_date, document_url
             FROM extension_certifications WHERE extension_id = ? LIMIT 1'
        );
        $stmtCert->execute([$extensionId]);
        $officialCertification = $stmtCert->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmtBor = $pdo->prepare(
            "SELECT reference_number, decision_date, document_url
             FROM extension_approvals
             WHERE extension_id = ? AND approval_stage = 'BOR' AND decision = 'Noted'
             ORDER BY approval_id DESC LIMIT 1"
        );
        $stmtBor->execute([$extensionId]);
        $officialBorNotation = $stmtBor->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        // The main proposal stays viewable if an older installation has no phase tables.
        error_log('Extension final documents load: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| OFFICIAL CALL FOR PROPOSALS
|--------------------------------------------------------------------------
| New submissions must belong to an open call within its date range.
| Older proposals may have no call_id; those drafts remain viewable,
| but must be linked to a call before they can be submitted.
*/
$extensionCall = null;
$callAcceptsSubmissions = false;
$callLoadError = false;

if (!empty($program['call_id'])) {
    try {
        $callQuery = $pdo->prepare("
            SELECT call_id, memo_title, memo_number, memo_url,
                   opens_on, closes_on, status,
                   (status = 'Open' AND opens_on <= CURDATE()
                    AND closes_on >= CURDATE()) AS accepts_submissions
            FROM extension_calls
            WHERE call_id = ?
            LIMIT 1
        ");
        $callQuery->execute([(int) $program['call_id']]);
        $extensionCall = $callQuery->fetch(PDO::FETCH_ASSOC) ?: null;
        $callAcceptsSubmissions = $extensionCall
            && (int) $extensionCall['accepts_submissions'] === 1;
    } catch (Throwable $e) {
        error_log('Extension call load failure: ' . $e->getMessage());
        $callLoadError = true;
    }
}

/*
|--------------------------------------------------------------------------
| LOAD DOCUMENTS
|--------------------------------------------------------------------------
*/

$documentStmt =
    $pdo->prepare("
        SELECT

            document_id,
            extension_id,
            uploaded_by,
            document_type,

            original_name,
            stored_name,
            file_path,

            file_extension,
            file_size,

            uploaded_at

        FROM extension_documents

        WHERE extension_id = ?

        ORDER BY
            uploaded_at DESC,
            document_id DESC
    ");


$documentStmt->execute([
    $extensionId
]);


$documents =
    $documentStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| SUPPORTING DOCUMENT URLS (NEW HTTPS LINKS + LEGACY LOCAL FILES)
|--------------------------------------------------------------------------
| The create form now stores HTTPS links in extension_documents.file_path.
| Older PDF/DOC/DOCX uploads are still supported by the existing viewer.
*/
function extensionStoredHttpsDocumentUrl(array $document): ?string
{
    $url = trim((string)($document['file_path'] ?? ''));
    if ($url === '' || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || preg_match('/\s/', $url)
    ) {
        return null;
    }
    return $url;
}

/*
|--------------------------------------------------------------------------
| DOCUMENT VIEWER / FILE HANDLER
|--------------------------------------------------------------------------
|
| file_mode=view
| - Shows an ASU-REMS document viewer page with a Download button.
|
| file_mode=inline
| - Streams PDF only inside the viewer iframe.
|
| file_mode=download
| - Downloads the actual uploaded file.
|
*/

$viewerDocument = null;
$viewerPhysicalPath = null;
$viewerExtension = null;
$viewerDownloadUrl = null;
$viewerInlineUrl = null;
$viewerPreviewText = null;
$viewerPreviewError = null;


function streamExtensionUploadedFile(
    string $physicalPath,
    string $originalName,
    string $extension,
    bool $inline = false
): never {

    $mimeTypes = [

        'pdf' =>
            'application/pdf',

        'doc' =>
            'application/msword',

        'docx' =>
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'

    ];


    $mimeType =
        $mimeTypes[$extension]
        ??
        'application/octet-stream';


    $downloadName =
        basename(
            $originalName
        );


    $asciiName =
        preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            $downloadName
        );


    header(
        'Content-Type: ' .
        $mimeType
    );


    header(
        'Content-Length: ' .
        filesize(
            $physicalPath
        )
    );


    header(
        'Content-Disposition: ' .
        (
            $inline
                ? 'inline'
                : 'attachment'
        )
        .
        '; filename="' .
        $asciiName .
        '"; filename*=UTF-8\'\'' .
        rawurlencode(
            $downloadName
        )
    );


    header(
        'X-Content-Type-Options: nosniff'
    );


    header(
        'Cache-Control: private, max-age=0, must-revalidate'
    );


    readfile(
        $physicalPath
    );


    exit;
}


function extractExtensionDocxText(
    string $physicalPath
): array {

    if (
        !class_exists(
            'ZipArchive'
        )
    ) {

        return [
            false,
            '',
            'DOCX preview requires the PHP ZIP extension. You can still download the uploaded file.'
        ];
    }


    $zip =
        new ZipArchive();


    if (
        $zip->open(
            $physicalPath
        )
        !== true
    ) {

        return [
            false,
            '',
            'Unable to read this DOCX file for browser preview. You can still download the uploaded file.'
        ];
    }


    $xml =
        $zip->getFromName(
            'word/document.xml'
        );


    $zip->close();


    if (
        $xml === false
        ||
        trim(
            $xml
        )
        === ''
    ) {

        return [
            false,
            '',
            'No readable document text was found in this DOCX file. You can still download the uploaded file.'
        ];
    }


    $xml =
        preg_replace(
            '/<w:tab\s*\/>/i',
            "\t",
            $xml
        );


    $xml =
        preg_replace(
            '/<w:br\s*\/>/i',
            "\n",
            $xml
        );


    $xml =
        preg_replace(
            '/<\/w:p>/i',
            "\n\n",
            $xml
        );


    $text =
        strip_tags(
            $xml
        );


    $text =
        html_entity_decode(
            $text,
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );


    $text =
        preg_replace(
            "/[ \t]+/",
            ' ',
            $text
        );


    $text =
        preg_replace(
            "/\n{3,}/",
            "\n\n",
            $text
        );


    $text =
        trim(
            $text
        );


    if (
        $text === ''
    ) {

        return [
            false,
            '',
            'The DOCX file was opened, but no readable text was detected. You can still download the uploaded file.'
        ];
    }


    return [
        true,
        $text,
        null
    ];
}


if (
    isset(
        $_GET['document_id']
    )
) {

    $documentId =
        (int)$_GET['document_id'];

    $fileMode =
        $_GET['file_mode']
        ?? 'view';


    if (
        $documentId <= 0
    ) {

        http_response_code(400);

        exit(
            'Invalid document request.'
        );
    }


    if (
        !in_array(
            $fileMode,
            [
                'view',
                'inline',
                'download'
            ],
            true
        )
    ) {

        $fileMode =
            'view';
    }


    $fileStmt =
        $pdo->prepare("
            SELECT

                document_id,
                extension_id,

                document_type,
                original_name,
                stored_name,
                file_path,

                file_extension,
                file_size,
                uploaded_at

            FROM extension_documents

            WHERE document_id = ?
              AND extension_id = ?

            LIMIT 1
        ");


    $fileStmt->execute([
        $documentId,
        $extensionId
    ]);


    $fileDocument =
        $fileStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$fileDocument) {

        http_response_code(404);

        exit(
            'Document record not found.'
        );
    }

    // New external supporting links do not have local upload files.
    // Keep old document_id links functional while allowing HTTPS links to open.
    $externalDocumentUrl = extensionStoredHttpsDocumentUrl($fileDocument);
    if ($externalDocumentUrl !== null) {
        header('Cache-Control: no-store');
        header('Location: ' . $externalDocumentUrl, true, 302);
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | RESOLVE PHYSICAL FILE
    |--------------------------------------------------------------------------
    */

    $relativePath =
        str_replace(
            [
                '\\',
                "\0"
            ],
            [
                '/',
                ''
            ],
            $fileDocument['file_path']
        );


    $relativePath =
        ltrim(
            $relativePath,
            '/'
        );


    if (
        !str_starts_with(
            $relativePath,
            'uploads/extension/'
        )
    ) {

        http_response_code(403);

        exit(
            'Invalid document path.'
        );
    }


    $projectRoot =
        realpath(
            __DIR__ . '/..'
        );


    $physicalPath =
        $projectRoot
        .
        DIRECTORY_SEPARATOR
        .
        str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $relativePath
        );


    if (
        !is_file(
            $physicalPath
        )
    ) {

        http_response_code(404);

        exit(
            'The uploaded document file could not be found.'
        );
    }


    $extension =
        strtolower(
            (string)$fileDocument[
                'file_extension'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    if (
        $fileMode === 'download'
    ) {

        streamExtensionUploadedFile(
            $physicalPath,
            $fileDocument['original_name'],
            $extension,
            false
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INLINE PDF STREAM FOR VIEWER IFRAME
    |--------------------------------------------------------------------------
    */

    if (
        $fileMode === 'inline'
    ) {

        if (
            $extension !== 'pdf'
        ) {

            http_response_code(415);

            exit(
                'Inline preview is available for PDF files only.'
            );
        }


        streamExtensionUploadedFile(
            $physicalPath,
            $fileDocument['original_name'],
            $extension,
            true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VIEWER PAGE
    |--------------------------------------------------------------------------
    */

    $viewerDocument =
        $fileDocument;

    $viewerPhysicalPath =
        $physicalPath;

    $viewerExtension =
        $extension;

    $viewerDownloadUrl =
        BASE_URL .
        '/extension/view.php?id=' .
        $extensionId .
        '&document_id=' .
        $documentId .
        '&file_mode=download';

    $viewerInlineUrl =
        BASE_URL .
        '/extension/view.php?id=' .
        $extensionId .
        '&document_id=' .
        $documentId .
        '&file_mode=inline';


    if (
        $viewerExtension === 'docx'
    ) {

        [
            $previewSuccess,
            $previewText,
            $previewError
        ] =
            extractExtensionDocxText(
                $viewerPhysicalPath
            );


        if (
            $previewSuccess
        ) {

            $viewerPreviewText =
                $previewText;

        } else {

            $viewerPreviewError =
                $previewError;
        }
    }


    if (
        $viewerExtension === 'doc'
    ) {

        $viewerPreviewError =
            'DOC files cannot be reliably previewed in the browser. Please download the uploaded file to open it in Microsoft Word or another compatible application.';
    }
}


/*
|--------------------------------------------------------------------------
| DOCUMENT REQUIREMENT
|--------------------------------------------------------------------------
*/

$hasProgramProposalDocument = false;


foreach (
    $documents
    as
    $document
) {

    if (
        $document['document_type']
        === 'Program Proposal'
    ) {

        $hasProgramProposalDocument = true;

        break;
    }
}


/*
|--------------------------------------------------------------------------
| SUBMIT PROGRAM FOR REVIEW
|--------------------------------------------------------------------------
| Successful submissions use POST/Redirect/GET (HTTP 303), so refreshing the
| page never repeats the POST or leaves a stale error on the screen.
| A notification failure must not undo an already-saved submission.
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    }


// Two Coordinator-only endorsement stages. A submitted HTTPS document,
// NOT a status-history marker, is required before ESO may proceed.
if (in_array($action, ['view_endorsement_link', 'coordinator_endorse'], true) && !$errors) {
    $stage = (string)$program['status'];
    $stageKey = extensionCoordinatorEndorsementStage($stage);
    $url = trim((string)($_POST['endorsement_document_url'] ?? ''));
    $dateTimeRaw = str_replace('T', ' ', trim((string)($_POST['endorsement_date'] ?? '')));
    if (strlen($dateTimeRaw) === 16) $dateTimeRaw .= ':00';
    $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateTimeRaw);
    $remarks = trim((string)($_POST['endorsement_remarks'] ?? ''));
    $viewKey = 'ext_coord_endorsement_view_' . (int)$user['user_id'] . '_' . $extensionId . '_' . $stageKey;
    if (!extensionIsCoordinator() || (int)$program['coordinator_id'] !== (int)$user['user_id']) {
        $errors[] = 'Only the assigned Extension Coordinator may submit an endorsement.';
    } elseif ($stageKey === null) {
        $errors[] = 'This proposal is not awaiting a Coordinator endorsement.';
    } elseif (!extensionCoordinatorValidEndorsementUrl($url)) {
        $errors[] = 'Enter a valid public HTTPS endorsement document link.';
    } elseif ($action === 'view_endorsement_link') {
        $_SESSION[$viewKey] = ['url' => $url, 'time' => time()];
        session_write_close();
        header('Location: ' . $url, true, 303);
        exit;
    } elseif (!$dateObj || $dateObj->format('Y-m-d H:i:s') !== $dateTimeRaw
              || $dateTimeRaw > date('Y-m-d H:i:s')) {
        $errors[] = 'Enter a valid endorsement date and time that is not in the future.';
    } elseif (mb_strlen($remarks) > 20000) {
        $errors[] = 'Endorsement remarks must not exceed 20,000 characters.';
    } else {
        $viewed = $_SESSION[$viewKey] ?? null;
        if (!is_array($viewed) || !hash_equals((string)($viewed['url'] ?? ''), $url)
            || time() - (int)($viewed['time'] ?? 0) > 3600) {
            $errors[] = 'You must click the eye button to view the link before submitting.';
        } else {
            $saved = false;
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT status, coordinator_id FROM extension_programs WHERE extension_id = ? FOR UPDATE');
                $lock->execute([$extensionId]);
                $current = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$current || (string)$current['status'] !== $stage
                    || (int)$current['coordinator_id'] !== (int)$user['user_id']) {
                    throw new RuntimeException('Proposal status changed. Refresh and try again.');
                }
                if (extensionCoordinatorHasEndorsed($pdo, $extensionId, (int)$user['user_id'], $stage)) {
                    throw new RuntimeException('Endorsement document has already been submitted for this stage.');
                }
                $insert = $pdo->prepare(
                    'INSERT INTO extension_coordinator_endorsements
                       (extension_id, coordinator_id, stage, document_url, endorsement_date, remarks)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$extensionId, (int)$user['user_id'], $stageKey,
                    $url, $dateTimeRaw, $remarks !== '' ? $remarks : null]);
                $history = $pdo->prepare(
                    'INSERT INTO extension_status_history (extension_id, changed_by, old_status, new_status, remarks)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $history->execute([$extensionId, (int)$user['user_id'], $stage, $stage,
                    extensionCoordinatorEndorsementMarker($stage) . ': Document submitted']);
                $pdo->commit();
                $saved = true;
                unset($_SESSION[$viewKey]);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Coordinator endorsement document failed: ' . $e->getMessage());
                $errors[] = $e instanceof RuntimeException ? $e->getMessage()
                    : 'Unable to save endorsement. Import the Coordinator endorsements SQL first.';
            }
            if ($saved) {
                try {
                    notifyUsersByRole($pdo, extensionOfficeRoles(),
                        'Coordinator endorsement document submitted',
                        fullName($user) . ' submitted the endorsement document for: ' . (string)$program['program_title'],
                        'extension', $extensionId,
                        BASE_URL . '/extension/view.php?id=' . $extensionId, (int)$user['user_id']);
                } catch (Throwable $noticeError) {
                    error_log('Coordinator endorsement notification error: ' . $noticeError->getMessage());
                }
                setFlash('success', 'Endorsement document submitted. ESO may now verify and continue processing.');
                header('Location: ' . BASE_URL . '/extension/view.php?id=' . $extensionId, true, 303);
                exit;
            }
        }
    }
}

    if ($action === 'submit_for_review' && !$errors) {

        // A repeated request for an already submitted proposal is not an error.
        // Only its authorized proponent may use this safe redirect.
        if (
            $program['status'] === 'Submitted'
            && extensionIsCoordinator()
            && (int)$program['coordinator_id'] === (int)$user['user_id']
        ) {
            header('Location: ' . BASE_URL . '/extension/view.php?id=' . $extensionId, true, 303);
            exit;
        }

        if (
            !extensionCanSubmitDraft(
                (string)$program['status'],
                (string)$user['role']
            )
        ) {
            $errors[] = 'Only the assigned proponent may submit a Draft extension proposal.';
        }

        if ((int)$program['coordinator_id'] !== (int)$user['user_id']) {
            $errors[] = 'You are not authorized to submit this extension program/project.';
        }

        if ($program['status'] !== 'Draft') {
            $errors[] = 'Only Draft extension program/project proposals can be submitted for review.';
        }

        if (!$extensionCall || !$callAcceptsSubmissions || $callLoadError) {
            $errors[] = 'This proposal must be linked to an active Call for Proposals within its submission period before submission.';
        }

        if (!$hasProgramProposalDocument) {
            $errors[] = 'A supporting Program/Project Proposal link is required before submission.';
        }

        if (!$errors) {
            $submissionSaved = false;

            // Keep only the database submission operations inside this try/catch.
            try {
                $pdo->beginTransaction();

                $checkStmt = $pdo->prepare("
                    SELECT coordinator_id, call_id, status
                    FROM extension_programs
                    WHERE extension_id = ?
                    FOR UPDATE
                ");
                $checkStmt->execute([$extensionId]);
                $currentRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$currentRecord
                    || (int)$currentRecord['coordinator_id'] !== (int)$user['user_id']
                    || $currentRecord['status'] !== 'Draft'
                ) {
                    throw new RuntimeException(
                        'Extension program/project is no longer available for submission.'
                    );
                }

                // Verify that the official call is still open at save time.
                $callCheck = $pdo->prepare("
                    SELECT call_id
                    FROM extension_calls
                    WHERE call_id = ?
                      AND status = 'Open'
                      AND opens_on <= CURDATE()
                      AND closes_on >= CURDATE()
                    FOR UPDATE
                ");
                $callCheck->execute([(int)($currentRecord['call_id'] ?? 0)]);

                if (!$callCheck->fetchColumn()) {
                    throw new RuntimeException(
                        'The associated Call for Proposals is no longer accepting submissions.'
                    );
                }

                $update = $pdo->prepare("
                    UPDATE extension_programs
                    SET status = 'Submitted', submitted_at = NOW()
                    WHERE extension_id = ?
                      AND coordinator_id = ?
                      AND status = 'Draft'
                ");
                $update->execute([$extensionId, (int)$user['user_id']]);

                if ($update->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Unable to update extension program/project status.'
                    );
                }

                $history = $pdo->prepare("
                    INSERT INTO extension_status_history
                        (extension_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, 'Draft', 'Submitted', ?)
                ");
                $history->execute([
                    $extensionId,
                    (int)$user['user_id'],
                    'Extension proposal submitted to the Extension Service Office for UERC processing.'
                ]);

                $pdo->commit();
                $submissionSaved = true;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                // Detailed information goes to the PHP error log, not the UI.
                error_log(
                    'Extension submission failed (ID ' . $extensionId . '): '
                    . $e->getMessage()
                );
                $errors[] = 'Unable to submit the extension program/project. Please try again.';
            }

            if ($submissionSaved) {
                // Notifications are secondary. They cannot roll back a saved proposal.
                try {
                    notifyUsersByRole(
                        $pdo,
                        extensionOfficeRoles(),
                        'New extension program/project submitted',
                        fullName($user) .
                            ' submitted an extension proposal for processing: ' .
                            $program['program_title'],
                        'extension',
                        $extensionId,
                        BASE_URL . '/extension/view.php?id=' . $extensionId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $e) {
                    error_log(
                        'Extension submission notification failed (ID ' . $extensionId . '): '
                        . $e->getMessage()
                    );
                }

                setFlash(
                    'success',
                    'Extension proposal submitted to the Extension Service Office.'
                );

                // Always load a clean GET after a successful submission.
                // The browser URL updates automatically; no manual URL paste.
                header('Location: ' . BASE_URL . '/extension/view.php?id=' . $extensionId, true, 303);
                exit;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| RELOAD AFTER POST
|--------------------------------------------------------------------------
*/

$program =
    loadExtensionProgram(
        $pdo,
        $extensionId
    );


/* Status history continues to be saved for the official workflow/audit log,
   but is intentionally not displayed on this program/project details page. */


/*
|--------------------------------------------------------------------------
| COORDINATOR NAME
|--------------------------------------------------------------------------
*/

$coordinatorName =
    trim(
        $program['first_name']
        .
        ' '
        .
        (
            !empty(
                $program['middle_name']
            )
                ? $program['middle_name']
                . ' '
                : ''
        )
        .
        $program['last_name']
    );




/*
|--------------------------------------------------------------------------
| SAVED PROPONENTS FOR THIS EXTENSION PROGRAM/PROJECT
|--------------------------------------------------------------------------
| Read the names saved by extension/create.php; the user account itself
| remains the coordinator. A missing table must not break the whole view.
*/
$programProponents = [];
$programProponentsLoadError = false;
try {
    $proponentsStmt = $pdo->prepare(
        'SELECT full_name FROM extension_program_proponents
         WHERE extension_id = ? ORDER BY sort_order ASC, proponent_id ASC'
    );
    $proponentsStmt->execute([$extensionId]);
    $programProponents = $proponentsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    error_log('Extension view proponents load error: ' . $e->getMessage());
    $programProponentsLoadError = true;
}

/*
|--------------------------------------------------------------------------
| STATUS CLASS / ICON
|--------------------------------------------------------------------------
*/

$statusClass =
    'extension-status-draft';

$statusIcon =
    'bi-pencil-square';


switch (
    $program['status']
) {

    case 'Submitted':

        $statusClass =
            'extension-status-submitted';

        $statusIcon =
            'bi-send-check';

        break;


    case 'Under Review':

        $statusClass =
            'extension-status-review';

        $statusIcon =
            'bi-search';

        break;


    case 'For Revision':

        $statusClass =
            'extension-status-revision';

        $statusIcon =
            'bi-arrow-repeat';

        break;


    case 'Approved':

        $statusClass =
            'extension-status-approved';

        $statusIcon =
            'bi-check-circle';

        break;


    case 'Ongoing':

        $statusClass =
            'extension-status-ongoing';

        $statusIcon =
            'bi-play-circle';

        break;


    case 'Completed':

        $statusClass =
            'extension-status-completed';

        $statusIcon =
            'bi-award';

        break;


    case 'Rejected':

        $statusClass =
            'extension-status-rejected';

        $statusIcon =
            'bi-x-circle';

        break;


    case 'Archived':

        $statusClass =
            'extension-status-archived';

        $statusIcon =
            'bi-archive';

        break;
}


/*
|--------------------------------------------------------------------------
| FILE SIZE
|--------------------------------------------------------------------------
*/

function extensionFileSize(
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


/*
|--------------------------------------------------------------------------
| EDIT PAGE AVAILABILITY
|--------------------------------------------------------------------------
*/

$editPageExists =
    is_file(
        __DIR__ .
        '/edit.php'
    );


/*
|--------------------------------------------------------------------------
| FLASH
|--------------------------------------------------------------------------
*/

$flash =
    getFlash();


/*
|--------------------------------------------------------------------------
| READ-ONLY EXTENSION DOCUMENT HISTORY (ALL SAVED LINKS)
|--------------------------------------------------------------------------
| Show every available link from the original proposal, Coordinator revisions
| and endorsements, ESO endorsement, combined LADCO/UADCO/BOR decisions,
| and the final official notice/certification. Nothing here writes workflow
| status or creates a link. Absent/legacy optional tables are skipped safely.
*/
$extensionHistoryGroups = [
    'proposal' => ['title' => 'Proposal Submission', 'icon' => 'bi-file-earmark-text', 'items' => []],
    'uerc' => ['title' => 'UERC Review and Revisions', 'icon' => 'bi-clipboard-check', 'items' => []],
    'pre_inhouse' => ['title' => 'First Coordinator Endorsement', 'icon' => 'bi-file-earmark-check', 'items' => []],
    'inhouse' => ['title' => 'University In-House Review and Revisions', 'icon' => 'bi-journal-check', 'items' => []],
    'final' => ['title' => 'Review and Final Endorsement', 'icon' => 'bi-file-check', 'items' => []],
    'approval' => ['title' => 'LADCO/UADCO/BOR', 'icon' => 'bi-award', 'items' => []],
    'certification' => ['title' => 'Notice of Approval / Certification', 'icon' => 'bi-patch-check', 'items' => []],
];

$extensionHistoryAdd = static function (string $group, string $title, string $url,
    string $source, ?string $date = null, ?string $detail = null) use (&$extensionHistoryGroups): void {
    $url = trim($url);
    if (!isset($extensionHistoryGroups[$group]) || $url === '') return;
    // Every external link must be a proper public HTTPS URL. The only allowed
    // local links are the existing authenticated /extension/view.php viewer.
    $isExternal = extensionStoredHttpsDocumentUrl(['file_path' => $url]) !== null;
    $localViewerPrefix = BASE_URL . '/extension/view.php?id=';
    $isViewer = str_starts_with($url, $localViewerPrefix)
        && (bool)preg_match('/^' . preg_quote($localViewerPrefix, '/') . '\\d+&document_id=\\d+&file_mode=view$/', $url);
    if (!$isExternal && !$isViewer) return;
    foreach ($extensionHistoryGroups[$group]['items'] as $existing) {
        if ($existing['url'] === $url && $existing['title'] === $title) return;
    }
    $dateLabel = '';
    if ($date !== null && trim($date) !== '') {
        $timestamp = strtotime($date);
        if ($timestamp !== false) $dateLabel = date('M d, Y', $timestamp);
    }
    $extensionHistoryGroups[$group]['items'][] = [
        'title' => $title, 'url' => $url, 'source' => $source,
        'date' => $dateLabel, 'detail' => trim((string)$detail),
    ];
};

// Original proposal and all extra supporting files (including older uploads).
foreach ($documents as $doc) {
    $savedUrl = extensionStoredHttpsDocumentUrl($doc);
    $link = $savedUrl ?? (BASE_URL . '/extension/view.php?id=' . $extensionId
        . '&document_id=' . (int)$doc['document_id'] . '&file_mode=view');
    $type = trim((string)($doc['document_type'] ?? '')) ?: 'Supporting Document';
    $typeLower = strtolower($type);
    $group = 'proposal';
    if (str_contains($typeLower, 'uerc')) $group = 'uerc';
    elseif (str_contains($typeLower, 'in-house') || str_contains($typeLower, 'in house')) $group = 'inhouse';
    elseif (str_contains($typeLower, 'endorsement')) $group = 'final';
    elseif (str_contains($typeLower, 'bor') || str_contains($typeLower, 'ladco') || str_contains($typeLower, 'uadco')) $group = 'approval';
    elseif (str_contains($typeLower, 'certification') || str_contains($typeLower, 'notice')) $group = 'certification';
    $title = $type === 'Program Proposal' ? 'Original Program/Project Proposal' : $type;
    $filename = trim((string)($doc['original_name'] ?? ''));
    $extensionHistoryAdd($group, $title, $link, 'Supporting Document',
        (string)($doc['uploaded_at'] ?? ''), $savedUrl === null ? $filename : null);
}

try {
    $q = $pdo->prepare('SELECT revision_stage, revised_proposal_url, submitted_at FROM extension_revisions WHERE extension_id = ? ORDER BY revision_id ASC');
    $q->execute([$extensionId]);
    $rounds = ['uerc' => 0, 'inhouse' => 0];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $rev) {
        $isUerc = strcasecmp(trim((string)$rev['revision_stage']), 'UERC') === 0;
        $group = $isUerc ? 'uerc' : 'inhouse';
        $rounds[$group]++;
        $extensionHistoryAdd($group,
            ($isUerc ? 'Revised Proposal — UERC' : 'Revised Proposal — In-House') . ' (Submission ' . $rounds[$group] . ')',
            (string)$rev['revised_proposal_url'], 'Extension Coordinator', (string)$rev['submitted_at']);
    }
} catch (Throwable $e) { error_log('Extension document history / revisions: ' . $e->getMessage()); }

try {
    $q = $pdo->prepare('SELECT stage, document_url, endorsement_date, submitted_at FROM extension_coordinator_endorsements WHERE extension_id = ? AND coordinator_id = ? ORDER BY endorsement_id ASC');
    $q->execute([$extensionId, (int)$program['coordinator_id']]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $endorse) {
        $first = (string)$endorse['stage'] === 'pre_inhouse';
        $extensionHistoryAdd($first ? 'pre_inhouse' : 'final',
            $first ? 'Coordinator Endorsement for In-House Review' : 'Coordinator Final Endorsement',
            (string)$endorse['document_url'], 'Extension Coordinator',
            (string)($endorse['submitted_at'] ?: $endorse['endorsement_date']));
    }
} catch (Throwable $e) { error_log('Extension document history / Coordinator endorsements: ' . $e->getMessage()); }

// ESO verification normally reuses the Coordinator's final endorsement URL.
// Keep one clickable link for that file, and mention ESO verification.
try {
    $q = $pdo->prepare('SELECT endorsement_document_url, endorsement_date FROM extension_endorsements WHERE extension_id = ? ORDER BY endorsement_id ASC');
    $q->execute([$extensionId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $esoEndorse) {
        $url = trim((string)($esoEndorse['endorsement_document_url'] ?? ''));
        $reused = false;
        foreach ($extensionHistoryGroups['final']['items'] as &$saved) {
            if ($saved['url'] === $url && $url !== '') {
                $saved['source'] = 'Coordinator · Verified by ESO';
                $reused = true;
                break;
            }
        }
        unset($saved);
        if (!$reused) {
            $extensionHistoryAdd('final', 'ESO Recorded Final Endorsement', $url,
                'Extension Service Office', (string)$esoEndorse['endorsement_date']);
        }
    }
} catch (Throwable $e) { error_log('Extension document history / ESO endorsement: ' . $e->getMessage()); }

try {
    $q = $pdo->prepare('SELECT approval_stage, reference_number, decision_date, document_url FROM extension_approvals WHERE extension_id = ? ORDER BY approval_id ASC');
    $q->execute([$extensionId]);
    $approvalRows = $q->fetchAll(PDO::FETCH_ASSOC);
    $ladco = null;
    $bor = null;
    foreach ($approvalRows as $row) {
        if ((string)$row['approval_stage'] === 'BOR') $bor = $row;
        if ((string)$row['approval_stage'] === 'LADCO_ADCO') $ladco = $row;
    }
    if ($bor && $ladco && trim((string)$bor['document_url']) === trim((string)$ladco['document_url'])) {
        $resolution = trim((string)($bor['reference_number'] ?? ''));
        $extensionHistoryAdd('approval', 'Official LADCO/UADCO/BOR Document', (string)$bor['document_url'],
            'Extension Service Office', (string)$bor['decision_date'],
            $resolution !== '' ? 'BOR Resolution No. ' . $resolution : null);
    } else {
        if ($ladco) $extensionHistoryAdd('approval', 'LADCO/UADCO Approval Document', (string)$ladco['document_url'],
            'Extension Service Office', (string)$ladco['decision_date']);
        if ($bor) {
            $resolution = trim((string)($bor['reference_number'] ?? ''));
            $extensionHistoryAdd('approval', 'BOR Notation Document', (string)$bor['document_url'],
                'Extension Service Office', (string)$bor['decision_date'],
                $resolution !== '' ? 'BOR Resolution No. ' . $resolution : null);
        }
    }
} catch (Throwable $e) { error_log('Extension document history / LADCO UADCO BOR: ' . $e->getMessage()); }

try {
    $q = $pdo->prepare('SELECT document_type, document_url, certification_date FROM extension_certifications WHERE extension_id = ? ORDER BY certification_id ASC');
    $q->execute([$extensionId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $cert) {
        $extensionHistoryAdd('certification', trim((string)$cert['document_type']) ?: 'Official Certification Document',
            (string)$cert['document_url'], 'Extension Service Office', (string)$cert['certification_date']);
    }
} catch (Throwable $e) { error_log('Extension document history / legacy certifications: ' . $e->getMessage()); }

try {
    $q = $pdo->prepare('SELECT notice_url, notice_date FROM extension_notices WHERE extension_id = ? ORDER BY notice_id ASC');
    $q->execute([$extensionId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $noticeRow) {
        $extensionHistoryAdd('certification', 'Notice of Approval / Certification',
            (string)$noticeRow['notice_url'], 'Extension Service Office', (string)$noticeRow['notice_date']);
    }
} catch (Throwable $e) { error_log('Extension document history / notices: ' . $e->getMessage()); }

$extensionHistoryCount = array_sum(array_map(
    static fn(array $group): int => count($group['items']), $extensionHistoryGroups
));

/*
|--------------------------------------------------------------------------
| ESO COMMENTS AND REMARKS — READ-ONLY FOR THE EXTENSION COORDINATOR
|--------------------------------------------------------------------------
| Show the ESO's actual saved comments across all workflow stages, including
| after approval. Review comments are NOT inferred from file uploads.
| Each query is optional for installations with older phase tables.
*/
$showEsoComments = extensionIsCoordinator()
    && (int)$program['coordinator_id'] === (int)$user['user_id'];
$extensionEsoComments = [];
$extensionEsoAddComment = static function (
    string $stage, string $comment, ?string $date, ?string $officer
) use (&$extensionEsoComments): void {
    $comment = trim($comment);
    if ($comment === '') return;
    $stamp = $date !== null && trim($date) !== '' ? strtotime($date) : false;
    $extensionEsoComments[] = [
        'stage' => $stage,
        'comment' => $comment,
        'date' => $stamp !== false ? date('M d, Y \a\t g:i A', $stamp) : '',
        'sort' => $stamp !== false ? $stamp : 0,
        'officer' => trim((string)$officer) ?: 'Extension Service Office',
    ];
};

if ($showEsoComments) {
    // Review comments remain visible after revision submission and approval.
    try {
        $stmt = $pdo->prepare(
            'SELECT er.review_stage, er.review_comments, er.reviewed_at,
                    u.first_name, u.last_name
             FROM extension_reviews er
             LEFT JOIN users u ON u.user_id = er.recorded_by
             WHERE er.extension_id = ?
             ORDER BY er.reviewed_at DESC, er.review_id DESC'
        );
        $stmt->execute([$extensionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $reviewStage = trim((string)$record['review_stage']);
            $heading = strcasecmp($reviewStage, 'UERC') === 0
                ? 'UERC Technical Review'
                : (strcasecmp($reviewStage, 'In-House') === 0
                    ? 'University In-House Review'
                    : ($reviewStage !== '' ? $reviewStage . ' Review' : 'Proposal Review'));
            $officer = trim((string)($record['first_name'] ?? '') . ' ' . (string)($record['last_name'] ?? ''));
            $extensionEsoAddComment($heading, (string)($record['review_comments'] ?? ''),
                (string)($record['reviewed_at'] ?? ''), $officer);
        }
    } catch (Throwable $e) {
        error_log('Extension view ESO remarks / reviews: ' . $e->getMessage());
    }

    // Chairperson/Dean/CD endorsement remarks were saved by ESO in the
    // status-history audit record, not in extension_reviews.
    try {
        $stmt = $pdo->prepare(
            'SELECT sh.remarks, u.first_name, u.last_name
             FROM extension_status_history sh
             INNER JOIN users u ON u.user_id = sh.changed_by
             WHERE sh.extension_id = ? AND u.role = ?
               AND sh.remarks LIKE ?'
        );
        $stmt->execute([$extensionId, 'extension_service_office', '%endorsement verified (%']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $full = trim((string)($record['remarks'] ?? ''));
            if (!preg_match('/^(Chairperson|Dean|CD) endorsement verified \(([^)]+)\):\s*(.+)$/si',
                $full, $parts)) continue;
            $officer = trim((string)($record['first_name'] ?? '') . ' ' . (string)($record['last_name'] ?? ''));
            $extensionEsoAddComment('Chairperson / Dean / CD Endorsement — ' . $parts[1],
                $parts[3], $parts[2], $officer);
        }
    } catch (Throwable $e) {
        error_log('Extension view ESO remarks / early endorsement: ' . $e->getMessage());
    }

    // The official ESO verification remarks are stored separately from the
    // Coordinator's own endorsement remarks.
    try {
        $stmt = $pdo->prepare(
            'SELECT en.endorsement_remarks, en.endorsement_date,
                    u.first_name, u.last_name
             FROM extension_endorsements en
             LEFT JOIN users u ON u.user_id = en.recorded_by
             WHERE en.extension_id = ? ORDER BY en.endorsement_id DESC'
        );
        $stmt->execute([$extensionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $officer = trim((string)($record['first_name'] ?? '') . ' ' . (string)($record['last_name'] ?? ''));
            $extensionEsoAddComment('Review and Final Endorsement',
                (string)($record['endorsement_remarks'] ?? ''),
                (string)($record['endorsement_date'] ?? ''), $officer);
        }
    } catch (Throwable $e) {
        error_log('Extension view ESO remarks / final endorsement: ' . $e->getMessage());
    }

    // One LADCO/UADCO/BOR submission writes two decision records. When the
    // ESO typed the same remarks for both, show them only once.
    try {
        $stmt = $pdo->prepare(
            'SELECT ea.approval_stage, ea.remarks, ea.decision_date,
                    u.first_name, u.last_name
             FROM extension_approvals ea
             LEFT JOIN users u ON u.user_id = ea.recorded_by
             WHERE ea.extension_id = ? ORDER BY ea.approval_id DESC'
        );
        $stmt->execute([$extensionId]);
        $seenApprovalRemarks = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $comment = trim((string)($record['remarks'] ?? ''));
            if ($comment === '' || isset($seenApprovalRemarks[$comment])) continue;
            $seenApprovalRemarks[$comment] = true;
            $officer = trim((string)($record['first_name'] ?? '') . ' ' . (string)($record['last_name'] ?? ''));
            $extensionEsoAddComment('LADCO/UADCO/BOR', $comment,
                (string)($record['decision_date'] ?? ''), $officer);
        }
    } catch (Throwable $e) {
        error_log('Extension view ESO remarks / approval: ' . $e->getMessage());
    }

    // Older proposal records may contain a separate certification.
    try {
        $stmt = $pdo->prepare(
            'SELECT ec.remarks, ec.certification_date, u.first_name, u.last_name
             FROM extension_certifications ec
             LEFT JOIN users u ON u.user_id = ec.recorded_by
             WHERE ec.extension_id = ? ORDER BY ec.certification_id DESC'
        );
        $stmt->execute([$extensionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $officer = trim((string)($record['first_name'] ?? '') . ' ' . (string)($record['last_name'] ?? ''));
            $extensionEsoAddComment('Official Certification', (string)($record['remarks'] ?? ''),
                (string)($record['certification_date'] ?? ''), $officer);
        }
    } catch (Throwable $e) {
        error_log('Extension view ESO remarks / legacy certification: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT en.remarks, en.notice_date, u.first_name, u.last_name
             FROM extension_notices en
             LEFT JOIN users u ON u.user_id = en.issued_by
             WHERE en.extension_id = ? ORDER BY en.notice_id DESC'
        );
        $stmt->execute([$extensionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $officer = trim((string)($record['first_name'] ?? '') . ' ' . (string)($record['last_name'] ?? ''));
            $extensionEsoAddComment('Notice of Approval / Certification',
                (string)($record['remarks'] ?? ''), (string)($record['notice_date'] ?? ''), $officer);
        }
    } catch (Throwable $e) {
        error_log('Extension view ESO remarks / notice: ' . $e->getMessage());
    }

    usort($extensionEsoComments,
        static fn(array $a, array $b): int => $b['sort'] <=> $a['sort']);
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


/*
|--------------------------------------------------------------------------
| LOAD LAYOUT
|--------------------------------------------------------------------------
*/

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

html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-summary,.extension-panel,.extension-document-summary,.extension-status-overview,.extension-official-documents,.extension-document-summary-tile,.workflow-card,.timeline-item) {background:#172f23!important;background-image:none!important;border-color:#395d46!important;color:#eaf3eb!important;box-shadow:none!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-panel-header,.extension-document-viewer-header) {background:#1d3c2b!important;border-color:#41624d!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-summary h2,.extension-panel-title,.extension-document-summary-name,.extension-status-label,.document-info strong,.extension-overview-row strong,.workflow-text h4) {color:#edf8ef!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-status-overview-content p,.extension-summary-meta,.document-info small,.info-item label,.timeline-item p,.extension-document-summary-label) {color:#bcd1c1!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-docx-page,.extension-document-frame,.extension-docx-preview) {background:#ffffff!important;color:#202820!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-docx-page *) {color:#202820!important}

</style>
<style>

/* =========================================================
   PAGE
========================================================= */

.extension-view {
    max-width: 1200px;
    margin: 0 auto;
}


.extension-view-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
}


.extension-view-heading h1 {
    margin: 0 0 5px;
    font-size: 25px;
    font-weight: 700;
}


.extension-view-heading p {
    margin: 0;
    color: var(--asu-muted);
    font-size: 11px;
}


.extension-heading-actions {
    display: flex;
    gap: 8px;
}


/* =========================================================
   ERROR
========================================================= */

.extension-errors {
    margin-bottom: 18px;
    padding: 14px 17px;
    border: 1px solid #edcccc;
    border-radius: 10px;
    background: #fff6f6;
    color: #873b3b;
    font-size: 11px;
}


/* =========================================================
   SUMMARY
========================================================= */

.extension-summary {
    margin-bottom: 16px;
    padding: 22px;
    border-radius: 15px;
    background:
        linear-gradient(
            120deg,
            #08742c,
            #086126
        );
    color: #ffffff;
}


.extension-summary-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
}


.extension-summary h2 {
    max-width: 850px;
    margin: 0 0 9px;
    color: #ffffff;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.4;
}


.extension-summary-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    color: rgba(255, 255, 255, .77);
    font-size: 10px;
}


/* =========================================================
   STATUS
========================================================= */

.extension-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 29px;
    padding: 5px 11px;
    border-radius: 50px;
    font-size: 9px;
    font-weight: 700;
    white-space: nowrap;
}


.extension-status-draft {
    background: #eef1ef;
    color: #626b64;
}


.extension-status-submitted {
    background: #e7f0ff;
    color: #365f99;
}


.extension-status-review {
    background: #fff4c8;
    color: #78620b;
}


.extension-status-revision {
    background: #fff0e2;
    color: #9a551c;
}


.extension-status-approved,
.extension-status-ongoing,
.extension-status-completed {
    background: #e4f4e8;
    color: #23703e;
}


.extension-status-rejected {
    background: #fde8e8;
    color: #a13939;
}


.extension-status-archived {
    background: #ebecef;
    color: #60656e;
}


/* =========================================================
   CURRENT STATUS
========================================================= */

.extension-status-overview {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding: 17px 19px;
    background: #ffffff;
    border: 1px solid var(--asu-border);
    border-radius: 13px;
}


.extension-status-overview-icon {
    width: 45px;
    height: 45px;
    min-width: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: #edf7ef;
    color: var(--asu-green);
    font-size: 19px;
}


.extension-status-overview-content {
    flex: 1;
    min-width: 0;
}


.extension-status-label {
    display: block;
    margin-bottom: 2px;
    color: #8a938c;
    font-size: 8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
}


.extension-status-overview h3 {
    margin: 0 0 4px;
    color: #293129;
    font-size: 14px;
    font-weight: 700;
}


.extension-status-overview p {
    max-width: 750px;
    margin: 0;
    color: #727c74;
    font-size: 9px;
    line-height: 1.65;
}


/* =========================================================
   GRID
========================================================= */

.extension-grid {
    display: grid;
    grid-template-columns:
        minmax(0, 1.6fr)
        minmax(280px, .75fr);
    gap: 20px;
    align-items: start;
}


.extension-column {
    display: grid;
    gap: 20px;
}


/* =========================================================
   PANEL
========================================================= */

.extension-panel {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid var(--asu-border);
    border-radius: 14px;
}


.extension-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    min-height: 60px;
    padding: 15px 20px;
    border-bottom: 1px solid #edf1ed;
}


.extension-panel-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: #283029;
    font-size: 13px;
    font-weight: 700;
}


.extension-panel-icon {
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


.extension-panel-body {
    padding: 20px;
}


/* =========================================================
   INFORMATION
========================================================= */

.info-grid {
    display: grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );
    gap: 18px;
}


.info-item label {
    display: block;
    margin-bottom: 5px;
    color: #8a938c;
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
}


.info-item strong {
    display: block;
    color: #343c36;
    font-size: 11px;
    line-height: 1.6;
}


/* =========================================================
   CONTENT
========================================================= */

.content-block {
    padding: 17px 0;
    border-bottom: 1px solid #edf1ed;
}


.content-block:first-child {
    padding-top: 0;
}


.content-block:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}


.content-block h4 {
    margin: 0 0 7px;
    font-size: 11px;
    font-weight: 700;
}


.content-block p {
    margin: 0;
    color: #626c64;
    font-size: 11px;
    line-height: 1.75;
    white-space: pre-line;
}


/* =========================================================
   DOCUMENTS
========================================================= */

.document-list {
    display: grid;
    gap: 10px;
}


.document-item {
    display: flex;
    align-items: center;

    gap: 11px;

    padding: 12px;

    border:
        1px solid #e4e9e5;

    border-radius: 9px;

    background: #fafcfa;
}


.document-item-link {
    width: 100%;

    color: inherit;

    text-decoration: none;

    cursor: pointer;

    transition:
        background .18s ease,
        border-color .18s ease,
        transform .18s ease;
}


.document-item-link:hover {
    color: inherit;

    background: #f2f8f3;

    border-color: #b7cfbd;

    transform: translateY(-1px);
}


.document-item-link:focus-visible {
    outline: 3px solid rgba(8, 117, 44, .16);

    outline-offset: 2px;
}


.document-icon {
    width: 38px;
    height: 38px;

    min-width: 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: #eaf5ec;

    color: var(--asu-green);
}


.document-info {
    flex: 1;

    min-width: 0;
}


.document-open-icon {
    width: 34px;
    height: 34px;

    min-width: 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: #edf7ef;

    color: var(--asu-green);

    font-size: 13px;
}


.document-info strong {
    display: block;

    overflow: hidden;

    margin-bottom: 3px;

    color: #343c36;

    font-size: 10px;

    text-overflow: ellipsis;

    white-space: nowrap;
}


.document-info small {
    color: #8a928c;

    font-size: 8px;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-data {
    padding: 20px 10px;
    color: #8a938c;
    font-size: 10px;
    text-align: center;
}


/* =========================================================
   HISTORY
========================================================= */

.timeline-item {
    padding: 12px 0;
    border-bottom: 1px solid #edf1ed;
}


.timeline-item:first-child {
    padding-top: 0;
}


.timeline-item:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}


.timeline-item strong {
    display: block;
    margin-bottom: 4px;
    font-size: 10px;
}


.timeline-item small {
    display: block;
    color: #8a938c;
    font-size: 8px;
}


.timeline-item p {
    margin: 5px 0 0;
    color: #656e67;
    font-size: 9px;
    line-height: 1.6;
}


/* =========================================================
   WORKFLOW
========================================================= */

.workflow-card {
    margin-top: 20px;
    padding: 18px 20px;
    border: 1px solid #e2e8e2;
    border-radius: 13px;
    background: #ffffff;
}


.workflow-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}


.workflow-text h4 {
    margin: 0 0 4px;
    color: #303830;
    font-size: 12px;
    font-weight: 700;
}


.workflow-text p {
    max-width: 680px;
    margin: 0;
    color: #7a837c;
    font-size: 9px;
    line-height: 1.6;
}


.workflow-buttons {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}


.workflow-notice {
    margin-top: 12px;
    padding: 10px 12px;
    border-left: 3px solid #d7ad23;
    border-radius: 7px;
    background: #fff9e5;
    color: #6e5d23;
    font-size: 9px;
    line-height: 1.6;
}


/* =========================================================
   DOCUMENT VIEWER
========================================================= */

.extension-document-viewer {
    overflow: hidden;

    background: #ffffff;

    border:
        1px solid
        var(--asu-border);

    border-radius: 14px;
}


.extension-document-viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 16px;

    padding: 15px 18px;

    border-bottom:
        1px solid #edf1ed;
}


.extension-document-viewer-title {
    display: flex;
    align-items: center;

    gap: 11px;

    min-width: 0;
}


.extension-document-viewer-title strong {
    display: block;

    overflow: hidden;

    color: #293129;

    font-size: 13px;

    text-overflow: ellipsis;

    white-space: nowrap;
}


.extension-document-viewer-title small {
    display: block;

    margin-top: 3px;

    color: #838c85;

    font-size: 9px;
}


.extension-document-viewer-actions {
    display: flex;
    align-items: center;

    gap: 8px;

    flex-shrink: 0;
}


.extension-document-frame {
    width: 100%;
    min-height: 760px;

    border: 0;

    background: #f8faf8;
}


.extension-docx-preview {
    min-height: 650px;

    padding: 28px;

    background: #f8faf8;
}


.extension-docx-page {
    max-width: 820px;

    margin: 0 auto;

    padding: 34px 40px;

    background: #ffffff;

    border:
        1px solid #e1e8e1;

    border-radius: 10px;

    color: #2f3831;

    font-size: 12px;

    line-height: 1.75;

    white-space: pre-wrap;

    box-shadow:
        0 10px 28px rgba(30, 64, 34, .08);
}


.extension-preview-message {
    min-height: 430px;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 34px;

    background: #f8faf8;

    text-align: center;
}


.extension-preview-message-box {
    max-width: 520px;

    padding: 28px;

    background: #ffffff;

    border:
        1px solid #e1e8e1;

    border-radius: 13px;
}


.extension-preview-message-icon {
    width: 58px;
    height: 58px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin: 0 auto 14px;

    border-radius: 50%;

    background: #edf7ef;

    color: var(--asu-green);

    font-size: 24px;
}


.extension-preview-message-box h3 {
    margin: 0 0 8px;

    color: #293129;

    font-size: 15px;

    font-weight: 700;
}


.extension-preview-message-box p {
    margin: 0 0 16px;

    color: #6f7871;

    font-size: 10px;

    line-height: 1.7;
}



/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .extension-grid {
        grid-template-columns: 1fr;
    }
}


@media (max-width: 700px) {

    .extension-view-heading,
    .extension-summary-top,
    .workflow-content {

        flex-direction: column;
    }


    .info-grid {
        grid-template-columns: 1fr;
    }


    .extension-heading-actions,
    .workflow-buttons {
        width: 100%;
        flex-direction: column;
    }


    .extension-heading-actions .btn,
    .workflow-buttons .btn {
        width: 100%;
    }


    .extension-status-overview {
        align-items: flex-start;
        flex-wrap: wrap;
    }
}



/* =========================================================
   FINAL EXTENSION PROGRAM DETAILS
   Full-width landscape • View/subpage styling
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

/* GLOBAL */

.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a,
.main-page table {
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

.extension-details-page {
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

.extension-view {
    width: 100% !important;
    max-width: 1320px !important;
    margin: 0 auto !important;
}

/* TOPBAR */

.asu-topbar {
    min-height: 76px !important;
    height: 76px !important;
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
}

.topbar-user-text strong {
    color: var(--asu-ink) !important;
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

/* SIMPLE SUBPAGE HEADING */

.extension-view-heading {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 18px !important;
    margin: 0 0 18px !important;
    padding: 4px 2px 0 !important;
}

.extension-view-heading > div:first-child {
    min-width: 0 !important;
}

.extension-view-heading h1 {
    margin: 0 0 7px !important;
    color: var(--asu-ink) !important;
    font-size: 28px !important;
    line-height: 1.15 !important;
    font-weight: 900 !important;
    letter-spacing: -.03em !important;
}

.extension-view-heading p {
    max-width: 760px !important;
    margin: 0 !important;
    color: #707a73 !important;
    font-size: 10.5px !important;
    line-height: 1.55 !important;
}

.extension-heading-actions {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
}

.extension-heading-actions .btn {
    width: auto !important;
    height: 36px !important;
    min-height: 36px !important;
    max-height: 36px !important;
    padding: 0 11px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    flex: 0 0 auto !important;
    border-radius: 9px !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
    white-space: nowrap !important;
}

.extension-heading-actions .btn i {
    margin: 0 !important;
    line-height: 1 !important;
}

/* ERROR / FLASH */

.extension-errors {
    margin-bottom: 16px !important;
    padding: 13px 15px !important;
    border: 1px solid rgba(188,58,58,.18) !important;
    border-left: 4px solid #b43c3c !important;
    border-radius: 12px !important;
    background: #fff7f7 !important;
    color: #7e3434 !important;
    font-size: 10px !important;
}

.alert {
    margin-bottom: 16px !important;
    border-radius: 12px !important;
    font-size: 10px !important;
}

/* SUMMARY */

.extension-summary {
    position: relative !important;
    overflow: hidden !important;
    margin-bottom: 16px !important;
    padding: 22px 24px !important;
    border: 0 !important;
    border-radius: 18px !important;
    background:
        linear-gradient(
            135deg,
            #005c26 0%,
            #007a33 67%,
            #2c9a39 100%
        ) !important;
    color: #fff !important;
    box-shadow: 0 16px 36px rgba(0,90,40,.16) !important;
}

.extension-summary::after {
    content: "" !important;
    position: absolute !important;
    right: -95px !important;
    top: -105px !important;
    width: 320px !important;
    height: 250px !important;
    border-radius: 50% !important;
    background: rgba(255,255,255,.045) !important;
    pointer-events: none !important;
}

.extension-summary > * {
    position: relative !important;
    z-index: 2 !important;
}

.extension-summary h2 {
    max-width: 1000px !important;
    margin: 0 0 9px !important;
    color: #fff !important;
    font-size: 21px !important;
    line-height: 1.35 !important;
    font-weight: 800 !important;
}

.extension-summary-meta {
    color: rgba(255,255,255,.80) !important;
    font-size: 9.5px !important;
}

.extension-summary-meta i {
    color: rgba(255,255,255,.86) !important;
}

/* SUMMARY STATUS — SUBTLE */

.extension-summary .extension-status {
    min-height: 29px !important;
    padding: 5px 10px !important;
    border: 1px solid rgba(255,255,255,.22) !important;
    border-radius: 999px !important;
    background: rgba(255,255,255,.12) !important;
    color: #fff !important;
    font-size: 8.5px !important;
    font-weight: 800 !important;
}

/* CURRENT STATUS */

.extension-status-overview {
    margin-bottom: 17px !important;
    padding: 15px 17px !important;
    gap: 13px !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 16px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 12px 28px rgba(16,24,16,.045) !important;
}

.extension-status-overview-icon {
    width: 44px !important;
    height: 44px !important;
    min-width: 44px !important;
    border-radius: 12px !important;
    background: linear-gradient(135deg,#007a33,#005c26) !important;
    color: #fff !important;
    font-size: 18px !important;
}

.extension-status-label {
    color: #7f8982 !important;
    font-size: 8px !important;
}

.extension-status-overview h3 {
    color: #263229 !important;
    font-size: 13px !important;
    font-weight: 800 !important;
}

.extension-status-overview p {
    max-width: 1050px !important;
    color: #657068 !important;
    font-size: 9.5px !important;
    line-height: 1.6 !important;
}

/* FULL-WIDTH LANDSCAPE STACK */

.extension-grid {
    display: grid !important;
    grid-template-columns: 1fr !important;
    gap: 17px !important;
    width: 100% !important;
    align-items: start !important;
}

.extension-column {
    display: grid !important;
    grid-template-columns: 1fr !important;
    gap: 17px !important;
    width: 100% !important;
}

.extension-grid > .extension-column {
    width: 100% !important;
    min-width: 0 !important;
}

/* PANELS */

.extension-panel {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    overflow: hidden !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 14px 34px rgba(16,24,16,.05) !important;
}

.extension-panel-header {
    min-height: 62px !important;
    padding: 14px 18px !important;
    border-bottom: 1px solid rgba(0,122,51,.09) !important;
    background: rgba(255,255,255,.86) !important;
}

.extension-panel-title {
    gap: 10px !important;
    color: var(--asu-ink) !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 14px !important;
    font-weight: 700 !important;
}

.extension-panel-icon {
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    border-radius: 10px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
    font-size: 16px !important;
}

.extension-panel-body {
    padding: 18px 20px 20px !important;
}

/* PROGRAM INFO + FUNDING — 4 COLUMNS */

.info-grid {
    display: grid !important;
    grid-template-columns: repeat(4,minmax(0,1fr)) !important;
    gap: 13px !important;
}

.info-item {
    min-width: 0 !important;
    min-height: 82px !important;
    padding: 12px 13px !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    border: 1px solid rgba(0,122,51,.08) !important;
    border-radius: 12px !important;
    background: #fbfcfb !important;
}

.info-item label {
    margin-bottom: 5px !important;
    color: #7e8981 !important;
    font-size: 8.5px !important;
    font-weight: 800 !important;
    letter-spacing: .035em !important;
}

.info-item strong {
    color: #263229 !important;
    font-size: 10.5px !important;
    line-height: 1.45 !important;
    font-weight: 700 !important;
}

/* CONTENT */

.content-block {
    padding: 15px 0 !important;
    border-bottom: 1px solid rgba(0,122,51,.07) !important;
}

.content-block:first-child {
    padding-top: 0 !important;
}

.content-block:last-child {
    padding-bottom: 0 !important;
    border-bottom: 0 !important;
}

.content-block h4 {
    margin: 0 0 6px !important;
    color: #35423a !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
}

.content-block p {
    color: #4d5850 !important;
    font-size: 10px !important;
    line-height: 1.7 !important;
}

/* COORDINATOR PANEL */

.extension-grid > .extension-column:last-child > .extension-panel:first-child .extension-panel-body {
    min-height: 86px !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    padding: 14px 18px !important;
}

.extension-grid > .extension-column:last-child > .extension-panel:first-child .extension-panel-body strong {
    color: #263229 !important;
    font-size: 11px !important;
}

/* DOCUMENTS — 2 COLUMNS */

.document-list {
    display: grid !important;
    grid-template-columns: repeat(2,minmax(0,1fr)) !important;
    gap: 11px !important;
}

.document-item {
    min-height: 66px !important;
    padding: 11px 12px !important;
    border: 1px solid rgba(0,122,51,.09) !important;
    border-radius: 12px !important;
    background: #fbfcfb !important;
}

.document-item-link:hover {
    background: #f2f8f3 !important;
    border-color: rgba(0,122,51,.22) !important;
    transform: none !important;
}

.document-icon {
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    border-radius: 10px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
}

.document-open-icon {
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    border-radius: 10px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
}

.document-info strong {
    font-size: 10.5px !important;
}

.document-info small {
    font-size: 8.5px !important;
}

/* STATUS HISTORY */

.timeline-item {
    padding: 12px 2px !important;
    border-bottom: 1px solid rgba(0,122,51,.07) !important;
}

.timeline-item:first-child {
    padding-top: 0 !important;
}

.timeline-item:last-child {
    padding-bottom: 0 !important;
    border-bottom: 0 !important;
}

.timeline-item strong {
    color: #2a352d !important;
    font-size: 10.5px !important;
}

.timeline-item small {
    color: #818b84 !important;
    font-size: 8.5px !important;
}

.timeline-item p {
    color: #657068 !important;
    font-size: 9px !important;
    line-height: 1.55 !important;
}

/* WORKFLOW */

.workflow-card {
    width: 100% !important;
    margin-top: 17px !important;
    padding: 17px 19px !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-left: 4px solid var(--asu-green) !important;
    border-radius: 16px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 12px 28px rgba(16,24,16,.045) !important;
}

.workflow-content {
    gap: 16px !important;
}

.workflow-text h4 {
    color: #263229 !important;
    font-size: 14px !important;
    font-weight: 800 !important;
}

.workflow-text p {
    max-width: 980px !important;
    color: #657068 !important;
    font-size: 10px !important;
    line-height: 1.6 !important;
}

.workflow-notice {
    margin-top: 10px !important;
    padding: 10px 12px !important;
    border-left-color: var(--asu-orange) !important;
    border-radius: 9px !important;
    background: #fff8ef !important;
    color: #6d573a !important;
    font-size: 9.5px !important;
}

.workflow-buttons .btn {
    min-height: 40px !important;
    padding: 0 13px !important;
    border-radius: 10px !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
}

/* STATUS BADGES OUTSIDE SUMMARY */

.extension-status-draft {
    background: #f1f3f1 !important;
    color: #606861 !important;
}

.extension-status-submitted,
.extension-status-review {
    background: #fff8d7 !important;
    color: #655315 !important;
}

.extension-status-revision {
    background: #fff2e5 !important;
    color: #8b541e !important;
}

.extension-status-approved,
.extension-status-ongoing,
.extension-status-completed {
    background: #edf7ef !important;
    color: var(--asu-green-dark) !important;
}

.extension-status-rejected,
.extension-status-archived {
    background: #eeeeee !important;
    color: #4b4f4c !important;
}

/* DOCUMENT VIEWER */

.extension-document-viewer {
    overflow: hidden !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 14px 34px rgba(16,24,16,.05) !important;
}

.extension-document-viewer-header {
    min-height: 64px !important;
    padding: 13px 16px !important;
    border-bottom: 1px solid rgba(0,122,51,.09) !important;
}

.extension-document-viewer-title strong {
    color: #263229 !important;
    font-size: 11px !important;
}

.extension-document-viewer-title small {
    color: #818b84 !important;
    font-size: 8.5px !important;
}

.extension-document-viewer-actions .btn {
    min-height: 36px !important;
    padding: 0 11px !important;
    border-radius: 9px !important;
    font-size: 9px !important;
    font-weight: 800 !important;
}

.extension-document-frame {
    min-height: 760px !important;
    background: #f5f8f5 !important;
}

.extension-docx-preview,
.extension-preview-message {
    background: #f5f8f5 !important;
}

.extension-docx-page,
.extension-preview-message-box {
    border-color: rgba(0,122,51,.10) !important;
    box-shadow: 0 12px 28px rgba(16,24,16,.05) !important;
}

/* BUTTONS */

.btn-asu,
.btn.btn-asu {
    border-color: var(--asu-green) !important;
    background: linear-gradient(135deg,var(--asu-green),var(--asu-green-dark)) !important;
    color: #fff !important;
    box-shadow: 0 8px 18px rgba(0,122,51,.13) !important;
}

.btn-outline-asu,
.btn.btn-outline-asu {
    border: 1px solid rgba(0,122,51,.22) !important;
    background: #fff !important;
    color: var(--asu-green) !important;
}

.btn-outline-asu:hover,
.btn.btn-outline-asu:hover {
    background: #f2f8f3 !important;
    color: var(--asu-green-dark) !important;
}

/* NOTICE */

.design-notice {
    margin-top: 16px !important;
    padding: 11px 13px !important;
    border: 1px solid rgba(0,122,51,.10) !important;
    border-left: 4px solid var(--asu-green) !important;
    border-radius: 12px !important;
    background: rgba(255,255,255,.88) !important;
    color: #657068 !important;
    font-size: 9.5px !important;
    line-height: 1.55 !important;
}

.design-notice strong {
    color: var(--asu-green-dark) !important;
}

/* MODAL */

.modal-content {
    overflow: hidden !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 16px !important;
    box-shadow: 0 20px 60px rgba(16,24,16,.18) !important;
}

.modal-title {
    color: var(--asu-ink) !important;
    font-size: 14px !important;
    font-weight: 800 !important;
}

.modal-body {
    color: #59635c !important;
    font-size: 10px !important;
}

.modal-footer .btn {
    min-height: 38px !important;
    border-radius: 9px !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
}

/* ONE COPYRIGHT ONLY */

.content-footer {
    display: none !important;
}

/* RESPONSIVE */

@media (max-width: 1050px) {
    .info-grid {
        grid-template-columns: repeat(2,minmax(0,1fr)) !important;
    }

    .document-list {
        grid-template-columns: 1fr !important;
    }

    .workflow-content {
        align-items: flex-start !important;
        flex-direction: column !important;
    }
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

    .extension-details-page {
        padding: 18px 14px 24px !important;
    }

    .extension-view-heading,
    .extension-summary-top,
    .workflow-content {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .extension-view-heading h1 {
        font-size: 24px !important;
    }

    .extension-heading-actions,
    .workflow-buttons {
        width: 100% !important;
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .extension-heading-actions .btn,
    .workflow-buttons .btn {
        width: 100% !important;
    }

    .info-grid {
        grid-template-columns: 1fr !important;
    }

    .extension-panel-body {
        padding: 16px !important;
    }
}

/* PROGRAM DOCUMENT SUMMARY — visible near the top, like Research Manuscript Link. */
.extension-document-summary {
    margin: 0 0 17px !important;
}
.extension-document-summary .extension-panel-header {
    min-height: 64px !important;
}
.extension-document-summary .extension-panel-body {
    padding: 17px 20px 20px !important;
}
.extension-document-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
    gap: 12px;
}
.extension-document-summary-tile {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
    padding: 15px;
    border: 1px solid rgba(0,122,51,.20);
    border-radius: 14px;
    background: #f3faf5;
}
.extension-document-summary-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 43px;
    width: 43px;
    height: 43px;
    border-radius: 12px;
    background: #e0f0e5;
    color: #007a33;
    font-size: 19px;
}
.extension-document-summary-info {
    min-width: 0;
    flex: 1;
}
.extension-document-summary-label {
    display: block;
    margin-bottom: 5px;
    color: #64766a;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .035em;
    text-transform: uppercase;
}
.extension-document-summary-name {
    display: block;
    margin-bottom: 5px;
    color: #1c2c22;
    font-size: 11px;
    line-height: 1.45;
    font-weight: 800;
    overflow-wrap: anywhere;
}
.extension-document-summary-link {
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    gap: 6px;
    margin: 0 !important;
    color: #007a33 !important;
    font-size: 11px;
    line-height: 1.4;
    font-weight: 800;
    text-decoration: underline !important;
    text-underline-offset: 2px;
}
.extension-document-summary-link:hover,
.extension-document-summary-link:focus-visible { color: #005c26 !important; }
.extension-document-summary-link i { font-size: 13px; line-height: 1; }
.extension-document-summary-meta {
    display: block;
    margin-top: 5px;
    color: #77857a;
    font-size: 9px;
}
.extension-document-summary-empty {
    margin: 0;
    padding: 11px 0;
    color: #657068;
    font-size: 11px;
}

/* Consistent center alignment for Edit Program/Project, Submit for Review, memo eye,
   and all other workflow and top action buttons. */
.extension-details-page .workflow-content {
    align-items: center !important;
}
.extension-details-page .workflow-buttons,
.extension-details-page .extension-heading-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    flex-wrap: wrap !important;
    gap: 9px !important;
}
.extension-details-page .workflow-buttons .btn,
.extension-details-page .extension-heading-actions .btn,
.extension-details-page .extension-document-viewer-actions .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    align-self: center !important;
    gap: 7px !important;
    min-height: 40px !important;
    height: 40px !important;
    padding: 0 14px !important;
    margin: 0 !important;
    line-height: 1.15 !important;
    vertical-align: middle !important;
    text-align: center !important;
    white-space: nowrap !important;
    border-radius: 10px !important;
}
.extension-details-page .workflow-buttons .btn i,
.extension-details-page .extension-heading-actions .btn i,
.extension-details-page .extension-document-viewer-actions .btn i {
    display: inline-block !important;
    flex: 0 0 auto !important;
    margin: 0 !important;
    line-height: 1 !important;
    vertical-align: middle !important;
}
@media (max-width: 760px) {
    .extension-document-summary-grid { grid-template-columns: 1fr; }
    .extension-details-page .workflow-content { align-items: stretch !important; }
    .extension-details-page .workflow-buttons,
    .extension-details-page .extension-heading-actions {
        align-items: stretch !important;
        justify-content: flex-start !important;
    }
    .extension-details-page .workflow-buttons .btn,
    .extension-details-page .extension-heading-actions .btn { width: 100% !important; }
}


/* Program Overview: current status on the LEFT, document summary on the RIGHT.
   Equal-height compact cards. Other page sections and workflow remain unchanged. */
.extension-details-page .extension-overview-row {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important;
    align-items: stretch !important;
    gap: 16px !important;
    width: 100% !important;
    margin: 0 0 17px !important;
}
.extension-details-page .extension-overview-row > .extension-status-overview,
.extension-details-page .extension-overview-row > .extension-document-summary {
    height: 100% !important;
    min-width: 0 !important;
    margin: 0 !important;
    box-sizing: border-box !important;
    border-radius: 17px !important;
}
.extension-details-page .extension-overview-row > .extension-status-overview {
    display: flex !important;
    align-items: center !important;
    gap: 13px !important;
    padding: 18px 20px !important;
}
.extension-details-page .extension-overview-row .extension-status-overview-content {
    min-width: 0 !important;
}
.extension-details-page .extension-overview-row .extension-status-overview p {
    max-width: none !important;
    line-height: 1.55 !important;
    margin: 0 !important;
}
.extension-details-page .extension-overview-row .extension-document-summary .extension-panel-header {
    min-height: 53px !important;
    padding: 10px 16px !important;
}
.extension-details-page .extension-overview-row .extension-document-summary .extension-panel-body {
    padding: 12px 16px 15px !important;
}
.extension-details-page .extension-overview-row .extension-document-summary-grid {
    grid-template-columns: minmax(0, 1fr) !important;
    gap: 9px !important;
}
.extension-details-page .extension-overview-row .extension-document-summary-tile {
    min-height: 72px !important;
    padding: 11px 12px !important;
    gap: 11px !important;
    align-items: center !important;
}
.extension-details-page .extension-overview-row .extension-document-summary-icon {
    width: 39px !important;
    min-width: 39px !important;
    height: 39px !important;
    flex-basis: 39px !important;
    font-size: 18px !important;
}
.extension-details-page .extension-overview-row .extension-document-summary-name {
    margin-bottom: 3px !important;
}
.extension-details-page .extension-overview-row .extension-document-summary-link {
    line-height: 1.3 !important;
}
/* Make the Draft/current-status content fill its half of the overview.
   More readable typography; keep the document card and workflows untouched. */
.extension-details-page .extension-overview-row > .extension-status-overview {
    gap: 17px !important;
    padding: 19px 22px !important;
}
.extension-details-page .extension-overview-row .extension-status-overview-icon {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;
    flex: 0 0 52px !important;
    font-size: 22px !important;
    align-self: center !important;
}
.extension-details-page .extension-overview-row .extension-status-overview-content {
    display: flex !important;
    flex: 1 1 auto !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-self: stretch !important;
    min-width: 0 !important;
    width: 100% !important;
}
.extension-details-page .extension-overview-row .extension-status-label {
    margin-bottom: 8px !important;
    font-size: 10.5px !important;
    font-weight: 800 !important;
    letter-spacing: .045em !important;
}
.extension-details-page .extension-overview-row .extension-status-overview h3 {
    margin: 0 0 10px !important;
    font-size: clamp(21px, 1.65vw, 26px) !important;
    font-weight: 900 !important;
    line-height: 1.2 !important;
    letter-spacing: -.025em !important;
}
.extension-details-page .extension-overview-row .extension-status-overview p {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    color: #4c6052 !important;
    font-size: 12.5px !important;
    font-weight: 500 !important;
    line-height: 1.65 !important;
    text-align: left !important;
}
@media (max-width: 520px) {
    .extension-details-page .extension-overview-row > .extension-status-overview {
        gap: 13px !important;
        padding: 16px !important;
    }
    .extension-details-page .extension-overview-row .extension-status-overview h3 {
        font-size: 22px !important;
    }
    .extension-details-page .extension-overview-row .extension-status-overview p {
        font-size: 11.5px !important;
    }
}

@media (max-width: 1150px) {
    .extension-details-page .extension-overview-row {
        grid-template-columns: minmax(0, 1fr) !important;
    }
    .extension-details-page .extension-overview-row > .extension-status-overview,
    .extension-details-page .extension-overview-row > .extension-document-summary {
        height: auto !important;
    }
}


/* Keep exactly the same vertical gap as the other detail cards.
   The shared .extension-panel margin: 0 !important previously overrode
   the inline margin-bottom and made these two sections touch. */
.extension-details-page .extension-panel.extension-official-documents {
    margin: 0 0 18px !important;
}

/* Official Notice and Certification — compact typography matching the other
   Extension detail cards. Scoped to this section only. */
.extension-details-page .extension-official-documents .extension-panel-body {
    padding: 12px 20px 15px !important;
}
.extension-details-page .extension-official-documents .extension-panel-body > .d-flex {
    min-height: 57px;
    margin-bottom: 0 !important;
    padding: 9px 0;
    align-items: center !important;
    gap: 12px !important;
}
.extension-details-page .extension-official-documents .extension-panel-body > .d-flex > div:first-child {
    min-width: 0;
}
.extension-details-page .extension-official-documents .extension-panel-body > .d-flex strong {
    display: block;
    margin: 0 0 4px !important;
    color: #263229 !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    line-height: 1.35 !important;
}
.extension-details-page .extension-official-documents .extension-panel-body > .d-flex small {
    display: block;
    margin: 0 !important;
    color: #78847b !important;
    font-size: 10px !important;
    font-weight: 400 !important;
    line-height: 1.45 !important;
}
.extension-details-page .extension-official-documents .extension-panel-body .btn {
    height: 36px !important;
    min-height: 36px !important;
    padding: 0 12px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    border-radius: 9px !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    line-height: 1.15 !important;
    white-space: nowrap;
}
.extension-details-page .extension-official-documents .extension-panel-body .btn i {
    margin: 0 !important;
    font-size: 12px !important;
    line-height: 1 !important;
}
.extension-details-page .extension-official-documents .extension-panel-body > hr {
    margin: 4px 0 !important;
    border-color: #e8eee9 !important;
    opacity: 1 !important;
}
.extension-details-page .extension-official-documents .extension-panel-body > p.small {
    margin-top: 10px !important;
    font-size: 9.5px !important;
    font-weight: 400 !important;
    line-height: 1.5 !important;
}
@media (max-width: 640px) {
    .extension-details-page .extension-official-documents .extension-panel-body {
        padding: 12px 15px !important;
    }
    .extension-details-page .extension-official-documents .extension-panel-body > .d-flex {
        align-items: flex-start !important;
    }
    .extension-details-page .extension-official-documents .extension-panel-body .btn {
        min-height: 34px !important;
        height: 34px !important;
    }
}


/* Revision action is shown only to the owning Extensionist/Coordinator.
   The edit.php page handles revised HTTPS link + eye verification and resubmission. */
.extension-details-page .extension-overview-row .extension-revision-action {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    align-self: flex-start !important;
    gap: 7px !important;
    min-height: 39px !important;
    height: 39px !important;
    margin: 14px 0 0 !important;
    padding: 0 14px !important;
    border-radius: 10px !important;
    font-size: 10.5px !important;
    font-weight: 800 !important;
    line-height: 1.1 !important;
    white-space: nowrap !important;
    text-align: center !important;
    text-decoration: none !important;
}
.extension-details-page .extension-overview-row .extension-revision-action i {
    margin: 0 !important;
    font-size: 13px !important;
    line-height: 1 !important;
}
@media (max-width: 520px) {
    .extension-details-page .extension-overview-row .extension-revision-action {
        max-width: 100% !important;
        white-space: normal !important;
    }
}



/* ============================================================
   ASU-REMS — EXTENSION DETAILS HEADER + DARK MODE FINISH
   Match the Extension Management / My Proposals green banner.
   Preserve all document and workflow actions. No animations.
   ============================================================ */
/* This title banner applies in BOTH Light Mode and Dark Mode. */
.main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading {
    position:relative !important;
    overflow:hidden !important;
    display:flex !important;
    align-items:center !important;
    justify-content:space-between !important;
    gap:18px !important;
    min-height:112px !important;
    margin:0 0 20px !important;
    padding:24px 30px !important;
    background:linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%) !important;
    border:0 !important;
    border-radius:19px !important;
    box-shadow:0 14px 35px rgba(0,90,40,.13) !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading::after {
    content:"";
    position:absolute;
    top:-120px;
    right:-110px;
    width:430px;
    height:280px;
    border-radius:42px;
    transform:rotate(-9deg);
    background:linear-gradient(135deg,rgba(255,213,31,.17),rgba(255,255,255,.04));
    pointer-events:none;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading > * {
    position:relative !important;
    z-index:1;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading h1 {
    margin:0 !important;
    color:#fff !important;
    font-size:30px !important;
    font-weight:900 !important;
    line-height:1.14 !important;
    letter-spacing:-.03em !important;
}
/* Match the uncluttered third screenshot, keep subtitle in source unchanged. */
.main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading p {
    display:none !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading .extension-heading-actions {
    position:relative !important;
    flex:0 0 auto !important;
    margin:0 !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading .extension-heading-actions .btn,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading .extension-heading-actions .btn {
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:6px !important;
    min-height:42px !important;
    height:42px !important;
    padding:0 15px !important;
    background:#ffd51f !important;
    background-image:none !important;
    border:0 !important;
    border-radius:11px !important;
    color:#003f1a !important;
    font-size:10px !important;
    font-weight:800 !important;
    box-shadow:none !important;
    transform:none !important;
    text-decoration:none !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading .extension-heading-actions .btn:hover,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading .extension-heading-actions .btn:hover {
    background:#f2ca09 !important;
    color:#003f1a !important;
    transform:none !important;
}
@media (max-width:700px) {
    .main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading {
        align-items:stretch !important;
        flex-direction:column !important;
        padding:22px 20px !important;
        min-height:96px !important;
    }
    .main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading h1 {font-size:25px !important;}
    .main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading .extension-heading-actions {width:100% !important;}
    .main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading .extension-heading-actions .btn {width:100% !important;}
}

/* Dark layout and panels — exact classes from the Extension Details source. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page {
    background:#0b1d15 !important;
    background-image:none !important;
    color:#e7f2e9 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .asu-topbar {
    background:#10271b !important;
    border-bottom-color:#395743 !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .asu-topbar .system-brand-copy strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .asu-topbar .topbar-user-text strong {
    color:#f1f7f1 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .asu-topbar .system-brand-copy span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .asu-topbar .topbar-user-text span {
    color:#bed2c3 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading {
    background:linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-view-heading h1 {
    color:#fff !important;
}
/* Keep the green title band with white text, not a flat dark card. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-summary {
    background:linear-gradient(112deg,#005c26 0%,#007a33 66%,#2c9a39 100%) !important;
    background-image:linear-gradient(112deg,#005c26 0%,#007a33 66%,#2c9a39 100%) !important;
    color:#fff !important;
    border:0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-summary :is(h2,.extension-summary-meta,.extension-summary-meta span,.extension-summary-meta i) {
    color:#fff !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-status-overview,.extension-panel,.extension-document-summary,.extension-official-documents,.extension-document-viewer,.workflow-card,.extension-preview-message-box) {
    background:#172f23 !important;
    background-image:none !important;
    color:#e8f4e9 !important;
    border-color:#3c5d47 !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-panel-header,.extension-document-viewer-header) {
    background:#1c3a2a !important;
    border-bottom-color:#41634c !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-panel-title,.extension-status-overview h3,.extension-status-label,.extension-document-summary-name,.content-block h4,.workflow-text h4,.extension-document-viewer-title strong,.extension-preview-message-box h3) {
    color:#f0f7f0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-status-overview p,.extension-status-overview-content p,.extension-document-summary-label,.extension-document-summary-meta,.info-item label,.content-block p,.workflow-text p,.extension-document-viewer-title small,.extension-preview-message-box p,.empty-data) {
    color:#c3d9c9 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-overview-row .extension-status-overview p {
    color:#c3d9c9 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-document-summary-tile,.document-item,.info-item) {
    background:#1e3b2c !important;
    background-image:none !important;
    border-color:#42664f !important;
    color:#edf5ed !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .info-item strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-grid > .extension-column:last-child > .extension-panel:first-child .extension-panel-body strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-official-documents .extension-panel-body > .d-flex strong {
    color:#f0f8ef !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-official-documents .extension-panel-body > .d-flex small,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-official-documents .extension-panel-body p.small,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-panel .text-muted {
    color:#bed4c3 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.document-info strong,.extension-document-summary-name,.timeline-item strong) {
    color:#edf7ee !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.document-info small,.timeline-item small,.timeline-item p) {
    color:#bed4c4 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.workflow-card,.content-block,.timeline-item) {
    border-color:#3e5f49 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .document-item-link:hover {
    background:#28513a !important;
    border-color:#649674 !important;
    transform:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.btn-outline-asu,.btn.btn-outline-asu,.extension-document-summary-link) {
    background:#234532 !important;
    background-image:none !important;
    color:#d9f6df !important;
    border-color:#577c60 !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.btn-outline-asu,.btn.btn-outline-asu,.extension-document-summary-link):hover {
    background:#2e573e !important;
    color:#fff !important;
    transform:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.design-notice,.workflow-notice) {
    background:#213c2c !important;
    border-color:#638260 !important;
    color:#deebd8 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .design-notice strong {
    color:#e7f5e7 !important;
}
/* The actual document pages stay white for readability and printing. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-docx-page,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-document-frame {
    background:#fff !important;
    color:#202820 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-docx-page * {
    color:#202820 !important;
}


/* ============================================================
   EXTENSION DETAILS — WHITE PROGRAM SUMMARY CARD
   Only the record summary (title, coordinator, dates, status)
   is white in both Light and Dark Mode. The green page-title
   banner and all other sections retain their existing styles.
   ============================================================ */
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-summary {
    background: #ffffff !important;
    background-image: none !important;
    border: 1px solid #dce9df !important;
    color: #213229 !important;
    box-shadow: 0 10px 25px rgba(15, 48, 28, .06) !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary::after {
    content: none !important;
    display: none !important;
}
/* No inner tinted rectangle behind the summary details.
   The summary card itself, text, and Approved badge are unaffected. */
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-top,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-top {
    background: transparent !important;
    background-image: none !important;
    border: 0 !important;
    box-shadow: none !important;
    outline: none !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-top::before,
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-top::after {
    content: none !important;
    display: none !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary h2,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-summary h2 {
    color: #1c3122 !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-meta,
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-meta span,
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-meta i,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-meta,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-meta span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-summary-meta i {
    color: #617267 !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-status,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-summary .extension-status {
    background: #e8f6ed !important;
    background-image: none !important;
    border: 1px solid #cce6d4 !important;
    color: #176539 !important;
    box-shadow: none !important;
}


/* Program/Project Coordinator: saved proponents belong inside this same panel. */
.extension-view-proponents {
    width:100%; margin-top:17px; padding-top:14px;
    border-top:1px solid rgba(0,122,51,.13);
}
.extension-view-proponents-header {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:5px 12px; margin-bottom:10px;
}
.extension-view-proponents-header > span {
    display:inline-flex; align-items:center; gap:7px;
    color:#005c26; font-size:11px; font-weight:800;
}
.extension-view-proponents-header small {color:#718075; font-size:9px;}
.extension-view-proponents-list {
    list-style:none; display:grid; grid-template-columns:repeat(2,minmax(0,1fr));
    gap:9px; margin:0; padding:0; counter-reset:extviewproponent;
}
.extension-view-proponents-list li {
    min-width:0; display:flex; align-items:center; gap:9px;
    padding:10px 11px; border:1px solid rgba(0,122,51,.11);
    border-radius:9px; background:#f9fcf9; color:#26382b;
    font-size:10.5px; overflow-wrap:anywhere;
}
.extension-view-proponents-list li::before {
    counter-increment:extviewproponent;
    content:counter(extviewproponent);
    flex-shrink:0; display:inline-flex; justify-content:center; align-items:center;
    width:23px; height:23px; border-radius:7px;
    background:#e4f3e7; color:#00652c; font-size:10px; font-weight:800;
}
.extension-view-proponents-message {margin:0; color:#768079; font-size:10px;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-view-proponents {
    border-color:#44624d!important;
}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-view-proponents-header > span {color:#def3e2!important;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-view-proponents-header small,
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-view-proponents-message {color:#bed1c3!important;}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-view-proponents-list li {
    background:#1c392b!important; border-color:#45644d!important; color:#eaf3eb!important;
}
html[data-rems-theme="dark"] .main-page.rems-extension-dark-scope .extension-view-proponents-list li::before {
    background:#31553c!important; color:#e1f2e4!important;
}
@media (max-width: 720px) {
    .extension-view-proponents-list {grid-template-columns:minmax(0,1fr);}
}


/* Coordinator details on the left; saved proponents on the right. */
.extension-details-page .extension-coordinator-layout {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: 24px;
    align-items: start;
    width: 100%;
}
.extension-details-page .extension-coordinator-details {
    min-width: 0;
    overflow-wrap: anywhere;
    padding: 9px 0;
}
.extension-details-page .extension-coordinator-layout .extension-view-proponents {
    min-width: 0;
    margin: 0 !important;
    padding: 2px 0 2px 24px !important;
    border-top: 0 !important;
    border-left: 1px solid rgba(0,122,51,.18);
}
.extension-details-page .extension-coordinator-layout .extension-view-proponents-list {
    grid-template-columns: minmax(0, 1fr);
}
@media (max-width: 760px) {
    .extension-details-page .extension-coordinator-layout {
        grid-template-columns: minmax(0, 1fr);
        gap: 15px;
    }
    .extension-details-page .extension-coordinator-layout .extension-view-proponents {
        padding: 15px 0 0 !important;
        border-left: 0 !important;
        border-top: 1px solid rgba(0,122,51,.18) !important;
    }
}


/* Keep a consistent card gap below the Coordinator endorsement panel.
   The global .extension-panel margin: 0 !important would otherwise make
   Program/Project Information touch the endorsement form. */
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel {
    margin: 0 0 18px !important;
}

/* Coordinator endorsement only: compact, consistent form presentation. */
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-panel-body {
    padding: 20px 24px 22px !important;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-intro {
    margin: 0 0 18px;
    max-width: 900px;
    font-family: "Century Gothic", sans-serif;
    font-size: 11.5px;
    line-height: 1.65;
    color: #607168;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-fields {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.35fr);
    column-gap: 20px;
    row-gap: 17px;
    align-items: start;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-field {
    min-width: 0;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-field .form-label {
    display: block;
    margin: 0 0 7px;
    color: #34453a;
    font-family: "Century Gothic", sans-serif;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.45;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-required {
    color: #be5b31;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-optional {
    color: #7e8b82;
    font-size: 10px;
    font-weight: 400;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-field .form-control {
    width: 100%;
    min-width: 0;
    height: 43px;
    min-height: 43px;
    padding: 10px 13px;
    border: 1px solid #d9e3db;
    border-radius: 10px;
    background: #fff;
    color: #263a2b;
    font-family: "Century Gothic", sans-serif;
    font-size: 11.5px;
    line-height: 1.45;
    box-shadow: none;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-field .form-control:focus {
    border-color: #5d9c71;
    box-shadow: 0 0 0 3px rgba(0,122,51,.09);
    outline: none;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-link-row {
    display: flex;
    align-items: stretch;
    gap: 8px;
    min-width: 0;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-link-row .form-control {
    flex: 1 1 auto;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel #coordEndorsementEye {
    display: inline-flex;
    flex: 0 0 43px;
    align-items: center;
    justify-content: center;
    width: 43px;
    min-width: 43px;
    height: 43px;
    padding: 0;
    border: 1px solid #c6dbcd;
    border-radius: 10px;
    background: #fff;
    color: #007a33;
    box-shadow: none;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel #coordEndorsementEye:hover {
    background: #eff7f0;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-hint {
    display: block;
    margin: 6px 0 0;
    color: #718075;
    font-family: "Century Gothic", sans-serif;
    font-size: 10px;
    line-height: 1.45;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-remarks {
    grid-column: 1 / -1;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-remarks textarea.form-control {
    height: 86px;
    min-height: 86px;
    padding-top: 11px;
    resize: vertical;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #e9efe9;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-footer-note {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #6e8073;
    font-family: "Century Gothic", sans-serif;
    font-size: 10.5px;
    line-height: 1.5;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel #coordEndorsementSubmit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 43px;
    padding: 0 17px;
    border-radius: 10px;
    font-family: "Century Gothic", sans-serif;
    font-size: 10.5px;
    font-weight: 800;
    white-space: nowrap;
    box-shadow: none;
}
.main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel #coordEndorsementSubmit:disabled {
    opacity: .68;
    cursor: not-allowed;
}
@media(max-width: 760px) {
    .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-panel-body {
        padding: 17px 16px 19px !important;
    }
    .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-fields {
        grid-template-columns: minmax(0, 1fr);
        gap: 15px;
    }
    .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-remarks {
        grid-column: auto;
    }
    .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-footer {
        align-items: stretch;
        flex-direction: column;
    }
    .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel #coordEndorsementSubmit {
        width: 100%;
    }
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-intro,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-hint,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-footer-note {
    color: #bfd3c3 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-field .form-label {
    color: #e5f1e7 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-optional {
    color: #b1c7b8 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-field .form-control {
    background: #1c392b !important;
    border-color: #4a7158 !important;
    color: #f3f9f2 !important;
    box-shadow: none !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel .extension-endorsement-footer {
    border-top-color: #41604b !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page #coordinatorEndorsementPanel #coordEndorsementEye {
    background: #203e2c !important;
    border-color: #527861 !important;
    color: #d7f0dc !important;
}

/* ASU-REMS: slightly larger eye icons; button dimensions and layout unchanged. */
.main-page.rems-extension-dark-scope .extension-details-page .workflow-buttons a[title="View Official Memo"] i.bi-eye,
.main-page.rems-extension-dark-scope .extension-details-page .extension-official-documents i.bi-eye {
    font-size: 18px !important;
    line-height: 1 !important;
}


/* Submitted endorsement overview: distribute content across the available card height.
   Only affects completed Coordinator endorsement displays; no workflow changes. */
.main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted {
    align-items: flex-start !important;
    gap: 15px !important;
    padding: 20px 21px !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted .extension-status-overview-icon {
    flex: 0 0 48px !important;
    width: 48px !important;
    min-width: 48px !important;
    height: 48px !important;
    align-self: flex-start !important;
    margin-top: 22px !important;
    font-size: 20px !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted .extension-status-overview-content {
    display: flex !important;
    flex: 1 1 auto !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-self: stretch !important;
    min-width: 0 !important;
    gap: 0 !important;
    padding: 0 !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted .extension-status-label {
    margin: 0 0 8px !important;
    color: #78877d !important;
    font-size: 9px !important;
    letter-spacing: .04em !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted h3 {
    margin: 0 0 8px !important;
    font-size: clamp(19px, 1.5vw, 23px) !important;
    font-weight: 800 !important;
    line-height: 1.28 !important;
    letter-spacing: -.02em !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted .extension-status-overview-content > p {
    max-width: none !important;
    margin: 0 0 14px !important;
    color: #526556 !important;
    font-size: 11.5px !important;
    font-weight: 500 !important;
    line-height: 1.6 !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-submitted-status-footer {
    display: grid;
    grid-template-columns: minmax(0, .84fr) minmax(0, 1.16fr);
    gap: 12px;
    width: 100%;
    margin-top: 0;
    padding-top: 11px;
    border-top: 1px solid #e3ebe4;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-submitted-status-detail {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 4px;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-submitted-status-detail-label {
    color: #79867d;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .035em;
    text-transform: uppercase;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-submitted-status-detail strong {
    color: #263e2e;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.5;
    overflow-wrap: anywhere;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted .extension-status-label,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-submitted-status-detail-label {
    color: #b8ceb9 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted .extension-status-overview-content > p {
    color: #c8d9cd !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-submitted-status-footer {
    border-top-color: #41604b !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-submitted-status-detail strong {
    color: #edf7ee !important;
}
@media (max-width: 740px) {
    .main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted {
        padding: 17px !important;
        gap: 12px !important;
    }
    .main-page.rems-extension-dark-scope .extension-details-page .extension-status-overview--submitted .extension-status-overview-icon {
        flex-basis: 42px !important;
        width: 42px !important;
        min-width: 42px !important;
        height: 42px !important;
        margin-top: 15px !important;
    }
    .main-page.rems-extension-dark-scope .extension-details-page .extension-submitted-status-footer {
        grid-template-columns: minmax(0, 1fr);
        gap: 10px;
    }
}



/* All statuses: the complete history holds supporting links, so the
   Current Program/Project Status uses the full available width. */
.main-page.rems-extension-dark-scope .extension-details-page .extension-overview-row.extension-overview-row--full {
    grid-template-columns: minmax(0, 1fr) !important;
}
.main-page.rems-extension-dark-scope .extension-details-page .extension-overview-row--full > .extension-status-overview {
    width: 100% !important;
    padding: 24px 28px !important;
}
@media (max-width: 520px) {
    .main-page.rems-extension-dark-scope .extension-details-page .extension-overview-row--full > .extension-status-overview {
        padding: 17px !important;
    }
}

/* Read-only, workflow-ordered document archive; scoped to Extension details. */
.extension-details-page .extension-history-jump {
    display:inline-flex;align-items:center;gap:6px;flex:0 0 auto;
    padding:7px 11px;border:1px solid #cfe3d3;border-radius:9px;
    color:#00652c;font-size:10px;font-weight:800;text-decoration:none;
    background:#f7fbf7;white-space:nowrap;
}
.extension-details-page .extension-history-jump:hover {background:#edf7ef;color:#005c26;}
.extension-details-page .extension-panel.extension-history-panel {margin:0 0 18px !important;scroll-margin-top:98px;}
.extension-details-page .extension-history-panel .extension-panel-header {flex-wrap:wrap;}
.extension-details-page .extension-history-count {
    color:#6c7b70;font-size:10px;font-weight:700;white-space:nowrap;
}
.extension-details-page .extension-history-intro {
    margin:0 0 17px;color:#617168;font-size:11px;line-height:1.65;
}
.extension-details-page .extension-history-grid {
    display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;
}
.extension-details-page .extension-history-stage {
    overflow:hidden;min-width:0;border:1px solid #e0ebe3;border-radius:13px;
    background:#fbfdfb;
}
.extension-details-page .extension-history-stage-heading {
    display:flex;align-items:center;gap:9px;padding:13px 14px;
    border-bottom:1px solid #e2ede5;
}
.extension-details-page .extension-history-stage-heading > i {color:#007a33;font-size:16px;}
.extension-details-page .extension-history-stage-heading strong {
    flex:1;min-width:0;color:#253b2a;font-size:11px;font-weight:800;line-height:1.5;
}
.extension-details-page .extension-history-items {padding:0 13px;}
.extension-details-page .extension-history-item {
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:12px 0;border-bottom:1px solid #e9f0e9;
}
.extension-details-page .extension-history-item:last-child {border-bottom:0;}
.extension-details-page .extension-history-item-text {flex:1;min-width:0;overflow-wrap:anywhere;}
.extension-details-page .extension-history-item-text strong {
    display:block;margin-bottom:5px;color:#273b2d;font-size:11px;font-weight:800;line-height:1.45;
}
.extension-details-page .extension-history-item-text small {
    display:block;color:#718075;font-size:9.5px;line-height:1.5;
}
.extension-details-page .extension-history-item-detail {margin-top:3px;}
.extension-details-page .extension-history-eye {
    width:39px;height:39px;min-width:39px;display:inline-flex;align-items:center;
    justify-content:center;border:1px solid #cfe1d3;border-radius:10px;
    background:#fff;color:#007a33;font-size:17px;text-decoration:none;
}
.extension-details-page .extension-history-eye:hover {background:#eff7f0;color:#005c26;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-history-stage,.extension-history-jump) {
    background:#1d392a!important;border-color:#43644d!important;color:#e6f3e6!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-history-stage-heading,.extension-history-item) {border-color:#3b5a46!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-history-intro,.extension-history-count,.extension-history-item-text small) {color:#bdd2c3!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page :is(.extension-history-stage-heading strong,.extension-history-item-text strong) {color:#eff8ef!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-history-eye {background:#244735!important;border-color:#587a61!important;color:#e5f6e9!important;}
@media(max-width:820px) {
    .extension-details-page .extension-history-grid {grid-template-columns:minmax(0,1fr);}
}

/* Compact accordion header, in the same style as Monitoring Details.
   Native details/summary keeps keyboard and touch operation without JavaScript. */
.extension-details-page .extension-history-panel > .extension-history-toggle {
    display:flex !important;
    align-items:center !important;
    justify-content:space-between !important;
    gap:16px !important;
    width:100%;
    min-height:78px !important;
    padding:14px 20px !important;
    margin:0 !important;
    list-style:none;
    cursor:pointer;
    user-select:none;
    background:#f4f9f5 !important;
    border-bottom:0 !important;
}
.extension-details-page .extension-history-toggle::-webkit-details-marker { display:none; }
.extension-details-page .extension-history-toggle::marker { content:""; }
.extension-details-page .extension-history-toggle:focus-visible {
    outline:2px solid #007a33;
    outline-offset:-4px;
}
.extension-details-page .extension-history-toggle-main {
    display:flex;align-items:center;gap:13px;min-width:0;
}
.extension-details-page .extension-history-toggle-main > .extension-panel-icon {
    width:48px !important;height:48px !important;min-width:48px !important;
    display:inline-flex;align-items:center;justify-content:center;
    border-radius:13px !important;
}
.extension-details-page .extension-history-toggle-copy {display:flex;flex-direction:column;min-width:0;gap:4px;}
.extension-details-page .extension-history-toggle-title {
    color:#173325;font-size:14px;font-weight:800;line-height:1.4;
}
.extension-details-page .extension-history-toggle-copy small {
    color:#6b8372;font-size:10.5px;line-height:1.5;
}
.extension-details-page .extension-history-toggle-side {
    display:flex;align-items:center;gap:20px;flex:0 0 auto;
}
.extension-details-page .extension-history-chevron {font-size:16px;color:#007a33;line-height:1;}
.extension-details-page .extension-history-panel[open] > .extension-history-toggle {
    border-bottom:1px solid #e1ebe3 !important;
}
.extension-details-page .extension-history-panel[open] .extension-history-chevron {transform:rotate(180deg);}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-history-panel > .extension-history-toggle {
    background:#1d3c2b !important;
    border-color:#41634c !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-history-toggle-title {color:#eef7ef !important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-history-toggle-copy small {color:#bed2c3 !important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-history-chevron {color:#c3e8cc !important;}
@media (max-width:650px) {
    .extension-details-page .extension-history-panel > .extension-history-toggle {padding:13px 15px !important;}
    .extension-details-page .extension-history-toggle-main > .extension-panel-icon {
        width:40px !important;height:40px !important;min-width:40px !important;
    }
    .extension-details-page .extension-history-toggle-title {font-size:12px;}
    .extension-details-page .extension-history-toggle-copy small {font-size:9.5px;}
    .extension-details-page .extension-history-toggle-side {gap:9px;}
    .extension-details-page .extension-history-count {display:none;}
}

/* Read-only ESO comments, visible to the owning Extension Coordinator. */
.main-page.rems-extension-dark-scope .extension-details-page .extension-panel.extension-eso-comments-panel {
    margin:0 0 18px !important;
}
.extension-details-page .extension-eso-comments-panel .extension-panel-header {
    min-height:67px !important;
    gap:12px !important;
}
.extension-details-page .extension-eso-comments-panel .extension-panel-body {
    padding:17px 19px 20px !important;
}
.extension-details-page .extension-eso-comments-intro {
    margin:0 0 15px;
    font-size:10.5px;
    color:#69766d;
    line-height:1.6;
}
.extension-details-page .extension-eso-comments-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:13px;
}
.extension-details-page .extension-eso-comment {
    min-width:0;
    padding:15px 16px;
    border:1px solid #dce9df;
    border-left:4px solid #007a33;
    border-radius:12px;
    background:#f9fcf9;
}
.extension-details-page .extension-eso-comment-heading {
    display:flex;align-items:flex-start;justify-content:space-between;
    gap:12px;margin-bottom:7px;
}
.extension-details-page .extension-eso-comment-heading strong {
    font-size:11.5px;font-weight:800;color:#20392a;line-height:1.45;
}
.extension-details-page .extension-eso-comment-meta {
    margin:0 0 9px;color:#758378;font-size:9.5px;line-height:1.5;
}
.extension-details-page .extension-eso-comment-body {
    margin:0;white-space:pre-wrap;overflow-wrap:anywhere;
    color:#3f5347;font-size:11px;line-height:1.7;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-eso-comment {
    background:#1c392b!important;border-color:#456c52!important;
    border-left-color:#78bd8d!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-eso-comment-heading strong {
    color:#eff8f0!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-eso-comments-intro,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-eso-comment-meta {
    color:#bfd3c3!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page .extension-eso-comment-body {
    color:#e1eee2!important;
}
@media(max-width:760px){
    .extension-details-page .extension-eso-comments-grid {grid-template-columns:minmax(0,1fr);}
    .extension-details-page .extension-eso-comments-panel .extension-panel-body {padding:15px!important;}
}
</style>


<div class="main-page rems-extension-dark-scope">


<header class="asu-topbar">


<div class="topbar-left">


<button
    id="sidebarToggle"
    type="button"
    class="sidebar-toggle"
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

<?= e(
    fullName($user)
); ?>

</strong>


<span>

<?= e(
    roleLabel(
        $user['role']
    )
); ?>

</span>


</div>


<div class="user-avatar">

<?= e(
    $initials
); ?>

</div>


</div>


</header>



<main class="page-content extension-details-page">


<div class="extension-view">


<?php if (
    $viewerDocument
): ?>


<!-- =====================================================
     DOCUMENT VIEWER
====================================================== -->

<div class="extension-view-heading">


<div>


<h1>

Document Viewer

</h1>


<p>

View or read the uploaded extension document
inside ASU-REMS, then download the file when needed.

</p>


</div>


<div class="extension-heading-actions">


<a
    href="<?= BASE_URL; ?>/extension/view.php?id=<?= $extensionId; ?>"
    class="btn btn-outline-asu"
>

<i class="bi bi-arrow-left me-1"></i>

Back to Program/Project Details

</a>


<a
    href="<?= e($viewerDownloadUrl); ?>"
    class="btn btn-asu"
>

<i class="bi bi-download me-1"></i>

Download

</a>


</div>


</div>


<section class="extension-document-viewer">


<div class="extension-document-viewer-header">


<div class="extension-document-viewer-title">


<div class="document-icon">


<?php if (
    $viewerExtension === 'pdf'
): ?>

<i class="bi bi-file-earmark-pdf"></i>

<?php else: ?>

<i class="bi bi-file-earmark-word"></i>

<?php endif; ?>


</div>


<div>


<strong>

<?= e(
    $viewerDocument[
        'original_name'
    ]
); ?>

</strong>


<small>

<?= e(
    $viewerDocument[
        'document_type'
    ]
); ?>

·

<?= e(
    extensionFileSize(
        $viewerDocument[
            'file_size'
        ]
        !== null
            ? (int)$viewerDocument[
                'file_size'
            ]
            : null
    )
); ?>

· Uploaded

<?= e(
    date(
        'M d, Y',
        strtotime(
            $viewerDocument[
                'uploaded_at'
            ]
        )
    )
); ?>

</small>


</div>


</div>


<div class="extension-document-viewer-actions">


<a
    href="<?= e($viewerDownloadUrl); ?>"
    class="btn btn-asu btn-sm"
>

<i class="bi bi-download me-1"></i>

Download File

</a>


</div>


</div>



<?php if (
    $viewerExtension === 'pdf'
): ?>


<iframe
    src="<?= e($viewerInlineUrl); ?>"
    class="extension-document-frame"
    title="<?= e(
        $viewerDocument[
            'original_name'
        ]
    ); ?>"
></iframe>


<?php elseif (
    $viewerExtension === 'docx'
    &&
    $viewerPreviewText !== null
): ?>


<div class="extension-docx-preview">


<div class="extension-docx-page">

<?= e(
    $viewerPreviewText
); ?>

</div>


</div>


<?php else: ?>


<div class="extension-preview-message">


<div class="extension-preview-message-box">


<div class="extension-preview-message-icon">

<i class="bi bi-file-earmark-word"></i>

</div>


<h3>

Preview Not Available

</h3>


<p>

<?= e(
    $viewerPreviewError
    ?: 'This document type cannot be previewed in the browser. Please download the file to open it.'
); ?>

</p>


<a
    href="<?= e($viewerDownloadUrl); ?>"
    class="btn btn-asu"
>

<i class="bi bi-download me-1"></i>

Download Document

</a>


</div>


</div>


<?php endif; ?>


</section>
<?php else: ?>


<!-- =====================================================
     HEADING
====================================================== -->

<div class="extension-view-heading">


<div>


<h1>

Extension Program/Project Details

</h1>


<p>

View program/project details, supporting links and the current workflow status.

</p>


</div>


<div class="extension-heading-actions">


<a
    href="<?= BASE_URL; ?>/extension/index.php"
    class="btn btn-outline-asu"
>

<i class="bi bi-arrow-left me-1"></i>

Back to Extension

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


<div class="extension-errors">


<strong>

Unable to complete the requested action.

</strong>


<ul class="mb-0 mt-2">


<?php foreach (
    $errors
    as
    $error
): ?>


<li>

<?= e(
    $error
); ?>

</li>


<?php endforeach; ?>


</ul>


</div>


<?php endif; ?>



<?php
$coordinatorEndorseStage = (string)$program['status'];
$coordinatorCanEndorse = extensionIsCoordinator()
    && (int)$program['coordinator_id'] === (int)$user['user_id']
    && in_array($coordinatorEndorseStage, [EXT_STATUS_PRE_INHOUSE_ENDORSEMENT, EXT_STATUS_ENDORSEMENT], true);
$coordinatorEndorseRecord = false;
if ($coordinatorCanEndorse) {
    try {
        $coordinatorEndorseRecord = extensionCoordinatorEndorsementRecord(
            $pdo, $extensionId, (int)$user['user_id'], $coordinatorEndorseStage
        );
    } catch (Throwable $e) {
        error_log('Coordinator endorsement document load: ' . $e->getMessage());
        $errors[] = 'Unable to load Coordinator endorsement. Import the endorsements SQL first.';
    }
}

// The official stage remains unchanged until ESO acts. This label is purely
// the Coordinator's visible status after a document has been submitted.
$coordinatorEndorseSubmitted = $coordinatorCanEndorse && (bool)$coordinatorEndorseRecord;
$coordinatorDisplayStatus = extensionStatusLabel((string)$program['status']);
if ($coordinatorEndorseSubmitted) {
    $coordinatorDisplayStatus = $coordinatorEndorseStage === EXT_STATUS_PRE_INHOUSE_ENDORSEMENT
        ? 'Submitted for Endorsement'
        : 'Submitted for Final Endorsement';
    $statusClass = 'extension-status-submitted';
    $statusIcon = 'bi-send-check';
}
?>

<!-- =====================================================
     SUMMARY
====================================================== -->

<section class="extension-summary">


<div class="extension-summary-top">


<div>


<h2>

<?= e(
    $program[
        'program_title'
    ]
); ?>

</h2>


<div class="extension-summary-meta">


<span>

<i class="bi bi-person-badge me-1"></i>

<?= e(
    $coordinatorName
); ?>

</span>


<?php if (
    !empty(
        $program[
            'college_name'
        ]
    )
): ?>


<span>

<i class="bi bi-building me-1"></i>

<?= e(
    $program[
        'college_name'
    ]
); ?>

</span>


<?php endif; ?>


<span>

<i class="bi bi-calendar3 me-1"></i>

Created

<?= e(
    date(
        'M d, Y',
        strtotime(
            $program[
                'created_at'
            ]
        )
    )
); ?>

</span>


<?php if (
    !empty(
        $program[
            'submitted_at'
        ]
    )
): ?>


<span>

<i class="bi bi-send-check me-1"></i>

Submitted

<?= e(
    date(
        'M d, Y',
        strtotime(
            $program[
                'submitted_at'
            ]
        )
    )
); ?>

</span>


<?php endif; ?>


</div>


</div>


<span
    class="
        extension-status
        <?= e(
            $statusClass
        ); ?>
    "
>

<?= e($coordinatorDisplayStatus); ?>

</span>


</div>


</section>

<!-- STATUS + SUPPORTING DOCUMENTS — COMPACT SIDE-BY-SIDE OVERVIEW -->
<div class="extension-overview-row extension-overview-row--full">

<!-- =====================================================
     CURRENT STATUS
====================================================== -->

<section class="extension-status-overview<?= $coordinatorEndorseSubmitted ? ' extension-status-overview--submitted' : ''; ?>">


<div class="extension-status-overview-icon">

<i
    class="
        bi
        <?= e(
            $statusIcon
        ); ?>
    "
></i>

</div>


<div class="extension-status-overview-content">


<span class="extension-status-label">

Current Program/Project Status

</span>


<h3>

<?= e($coordinatorDisplayStatus); ?>

</h3>


<p>


<?php if ($coordinatorEndorseSubmitted): ?>
    <?= $coordinatorEndorseStage === EXT_STATUS_PRE_INHOUSE_ENDORSEMENT
        ? 'The Coordinator endorsement has been submitted to the Extension Service Office for verification before University In-House Review. The submitted document is now on record and available for ESO to examine and confirm. Once ESO completes the endorsement verification, the proposal may proceed to University In-House Review. No additional action is required from the Extension Coordinator unless ESO requests further information or revisions.'
        : 'The final endorsed proposal has been submitted to the Extension Service Office for checking before the approval process continues. ESO will review the endorsed document and confirm that the required revisions and supporting information have been addressed. Following ESO verification and final endorsement, the proposal may proceed to the combined LADCO/UADCO/BOR stage. The Extension Coordinator may monitor the proposal status and respond if additional information is requested.'; ?>
<?php else: ?>
<?php switch (
    $program['status']
):


case 'Draft':
?>

The extension program/project is still being prepared.
The assigned proponent may review
the details and submit the proposal when the
required supporting document is available.

<?php
break;


case 'Submitted':
?>

The extension program/project has been formally submitted
and is waiting for UERC processing by the Extension Service Office.

<?php
break;


case 'Under Review':
?>

The extension program/project is currently under
Extension Service Office processing.

<?php
break;


case 'For UERC Revision':
?>

UERC requested revisions. Review the comments and
submit the revised proposal for endorsement before In-House Review.

<?php
break;


case 'For In-House Revision':
?>

In-House Review requested revisions. Review the comments,
then submit your revised paper for final endorsement.

<?php
break;


case 'For Pre-In-House Endorsement':
?>

The reviewed UERC proposal is ready for Coordinator endorsement.
Once endorsed, ESO can verify the endorsement and proceed to In-House Review.

<?php
break;

case 'For Endorsement':
?>

The In-House review is complete. The Coordinator must review and
endorse the proposal before ESO records the final endorsement.

<?php
break;

case 'For Revision':
?>

The program/project has been returned for revision.
The proponent will need to address
the review remarks before resubmission.

<?php
break;


case 'Approved':
?>

The extension program/project has been approved.
Implementation and program/project monitoring may
proceed in the succeeding workflow.

<?php
break;


case 'Ongoing':
?>

The extension program/project is currently being
implemented and monitored.

<?php
break;


case 'Completed':
?>

The extension program/project has completed the
required implementation and completion process.

<?php
break;


case 'Rejected':
?>

The extension program/project was not approved.
Review information will be available in the
administrative evaluation workflow.

<?php
break;


case 'Archived':
?>

This extension program/project has been archived
as part of the institutional extension records.

<?php
break;


default:
?>

Current extension workflow information
is shown on this page.

<?php endswitch; ?>
<?php endif; ?>


</p>

<?php if ($coordinatorEndorseSubmitted): ?>
    <div class="extension-submitted-status-footer" aria-label="Endorsement processing details">
        <div class="extension-submitted-status-detail">
            <span class="extension-submitted-status-detail-label">Submitted on</span>
            <strong><?= e(date('M d, Y \a\t g:i A', strtotime((string)$coordinatorEndorseRecord['submitted_at']))); ?></strong>
        </div>
        <div class="extension-submitted-status-detail">
            <span class="extension-submitted-status-detail-label">Next processing step</span>
            <strong><?= $coordinatorEndorseStage === EXT_STATUS_PRE_INHOUSE_ENDORSEMENT
                ? 'ESO Verification &amp; In-House Review'
                : 'ESO Final Endorsement &amp; LADCO/UADCO/BOR'; ?></strong>
        </div>
    </div>
<?php endif; ?>

<?php if (
    extensionIsCoordinator()
    && (int) $program['coordinator_id'] === (int) $user['user_id']
    && in_array((string) $program['status'], ['For UERC Revision', 'For In-House Revision'], true)
    && $editPageExists
): ?>
    <a href="<?= BASE_URL; ?>/extension/edit.php?id=<?= $extensionId; ?>"
       class="btn btn-asu extension-revision-action"
       title="Edit revised proposal and resubmit"
       aria-label="Edit and resubmit extension proposal">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        Edit and Resubmit
    </a>
<?php endif; ?>




</div>


</section>


</div>

<?php if ($showEsoComments && $extensionEsoComments): ?>
<!-- ESO's actual review and processing remarks remain available at every stage. -->
<details class="extension-panel extension-history-panel extension-eso-comments-panel" id="extensionEsoComments" aria-label="ESO review comments and remarks" open>
    <summary class="extension-panel-header extension-history-toggle">
        <span class="extension-history-toggle-main">
            <span class="extension-panel-icon"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span>
            <span class="extension-history-toggle-copy">
                <strong class="extension-history-toggle-title">ESO Review Comments and Remarks</strong>
                <small>View the official comments recorded by the Extension Service Office</small>
            </span>
        </span>
        <span class="extension-history-toggle-side">
            <span class="extension-history-count"><?= count($extensionEsoComments); ?> saved remark<?= count($extensionEsoComments) === 1 ? '' : 's'; ?></span>
            <i class="bi bi-chevron-down extension-history-chevron" aria-hidden="true"></i>
        </span>
    </summary>
    <div class="extension-panel-body">
        <p class="extension-eso-comments-intro">Official comments recorded by the Extension Service Office. These remain available for reference, even after the proposal is approved.</p>
        <div class="extension-eso-comments-grid">
            <?php foreach ($extensionEsoComments as $esoComment): ?>
            <article class="extension-eso-comment">
                <div class="extension-eso-comment-heading">
                    <strong><?= e($esoComment['stage']); ?></strong>
                </div>
                <p class="extension-eso-comment-meta">
                    <?= e($esoComment['officer']); ?><?php if ($esoComment['date'] !== ''): ?> · <?= e($esoComment['date']); ?><?php endif; ?>
                </p>
                <p class="extension-eso-comment-body"><?= e($esoComment['comment']); ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</details>
<?php endif; ?>

<!-- Collapsible, read-only link archive for every extension status. -->
<details class="extension-panel extension-history-panel" id="extensionCompleteDocumentHistory" aria-label="Complete extension document history">
    <summary class="extension-panel-header extension-history-toggle">
        <span class="extension-history-toggle-main">
            <span class="extension-panel-icon"><i class="bi bi-collection" aria-hidden="true"></i></span>
            <span class="extension-history-toggle-copy">
                <strong class="extension-history-toggle-title">Complete Supporting Document History</strong>
                <small>View the saved proposal, revision, endorsement, and approval documents</small>
            </span>
        </span>
        <span class="extension-history-toggle-side">
            <span class="extension-history-count"><?= (int)$extensionHistoryCount; ?> saved link<?= $extensionHistoryCount === 1 ? '' : 's'; ?></span>
            <i class="bi bi-chevron-down extension-history-chevron" aria-hidden="true"></i>
        </span>
    </summary>
    <div class="extension-panel-body">
        <?php if ($extensionHistoryCount > 0): ?>
        <p class="extension-history-intro">All available proposal, revision, endorsement, approval, and certification documents are kept here for review. Only actually submitted links appear; this section is read-only.</p>
        <div class="extension-history-grid">
            <?php foreach ($extensionHistoryGroups as $historyStage): ?>
                <?php if (!$historyStage['items']) continue; ?>
                <div class="extension-history-stage">
                    <div class="extension-history-stage-heading">
                        <i class="bi <?= e($historyStage['icon']); ?>" aria-hidden="true"></i>
                        <strong><?= e($historyStage['title']); ?></strong>
                    </div>
                    <div class="extension-history-items">
                        <?php foreach ($historyStage['items'] as $historyItem): ?>
                        <div class="extension-history-item">
                            <div class="extension-history-item-text">
                                <strong><?= e($historyItem['title']); ?></strong>
                                <small><?= e($historyItem['source']); ?><?php if ($historyItem['date'] !== ''): ?> · <?= e($historyItem['date']); ?><?php endif; ?></small>
                                <?php if ($historyItem['detail'] !== ''): ?>
                                    <small class="extension-history-item-detail"><?= e($historyItem['detail']); ?></small>
                                <?php endif; ?>
                            </div>
                            <a class="extension-history-eye" href="<?= e($historyItem['url']); ?>" target="_blank" rel="noopener noreferrer"
                               title="View <?= e($historyItem['title']); ?>" aria-label="View <?= e($historyItem['title']); ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="extension-history-intro" style="margin-bottom:0">No submitted document links are available yet.</p>
        <?php endif; ?>
    </div>
</details>

<?php if ($coordinatorCanEndorse && !$coordinatorEndorseRecord): ?>
<section class="extension-panel" id="coordinatorEndorsementPanel">
    <div class="extension-panel-header">
        <h2 class="extension-panel-title">
            <span class="extension-panel-icon"><i class="bi bi-file-earmark-check" aria-hidden="true"></i></span>
            <?= $coordinatorEndorseStage === EXT_STATUS_PRE_INHOUSE_ENDORSEMENT
                ? 'Endorsement for In-House Review' : 'Review and Final Endorsement'; ?>
        </h2>
    </div>
    <div class="extension-panel-body">
            <p class="extension-endorsement-intro">Provide the endorsement details and the official supporting document link to forward this proposal to the Extension Service Office.</p>
            <form method="POST" id="coordinatorEndorsementForm"
                  action="<?= BASE_URL; ?>/extension/view.php?id=<?= $extensionId; ?>">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                <input type="hidden" name="action" value="coordinator_endorse">
                <div class="extension-endorsement-fields">
                    <div class="extension-endorsement-field">
                        <label for="coordEndorsementDate" class="form-label">Endorsement Date and Time <span class="extension-endorsement-required">*</span></label>
                        <input id="coordEndorsementDate" type="datetime-local" name="endorsement_date"
                               class="form-control" max="<?= e(date('Y-m-d\TH:i')); ?>"
                               value="<?= e((string)($_POST['endorsement_date'] ?? '')); ?>" required>
                    </div>
                    <div class="extension-endorsement-field">
                        <label for="coordEndorsementUrl" class="form-label">Endorsement Document HTTPS Link <span class="extension-endorsement-required">*</span></label>
                        <div class="extension-endorsement-link-row">
                            <input id="coordEndorsementUrl" type="url" name="endorsement_document_url"
                                   class="form-control" placeholder="https://" pattern="https://.*" maxlength="1000"
                                   value="<?= e((string)($_POST['endorsement_document_url'] ?? '')); ?>" required>
                            <button id="coordEndorsementEye" type="button"
                                    class="btn btn-outline-asu extension-eye-action"
                                    aria-label="View endorsement document link" title="View endorsement document link">
                                <i class="bi bi-eye" aria-hidden="true" style="font-size:18px"></i>
                            </button>
                        </div>
                        <small class="extension-endorsement-hint">You must open the link using the eye button before submitting.</small>
                    </div>
                    <div class="extension-endorsement-field extension-endorsement-remarks">
                        <label for="coordEndorsementRemarks" class="form-label">Endorsement Remarks <span class="extension-endorsement-optional">(Optional)</span></label>
                        <textarea id="coordEndorsementRemarks" name="endorsement_remarks"
                                  class="form-control" rows="3" maxlength="20000"
                                  placeholder="Enter any additional endorsement remarks"><?= e((string)($_POST['endorsement_remarks'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="extension-endorsement-footer">
                    <span class="extension-endorsement-footer-note"><i class="bi bi-info-circle" aria-hidden="true"></i> ESO processing resumes after submission.</span>
                    <button id="coordEndorsementSubmit" type="submit" class="btn btn-asu" disabled>
                        <i class="bi bi-send" aria-hidden="true"></i>
                        <?= $coordinatorEndorseStage === EXT_STATUS_PRE_INHOUSE_ENDORSEMENT
                            ? 'Submit In-House Endorsement' : 'Submit Final Endorsement'; ?>
                    </button>
                </div>
            </form>
    </div>
</section>
<?php endif; ?>

<?php if ($coordinatorCanEndorse && !$coordinatorEndorseRecord): ?>
<script>
(function(){
    'use strict';
    const form=document.getElementById('coordinatorEndorsementForm');
    const input=document.getElementById('coordEndorsementUrl');
    const eye=document.getElementById('coordEndorsementEye');
    const submit=document.getElementById('coordEndorsementSubmit');
    if(!form||!input||!eye||!submit)return;
    let viewed='';
    input.addEventListener('input',function(){if(input.value.trim()!==viewed){viewed='';submit.disabled=true;}});
    eye.addEventListener('click',function(){
        const url=input.value.trim();
        if(!input.checkValidity()){input.reportValidity();return;}
        try{const u=new URL(url);if(u.protocol!=='https:'||!u.hostname)throw Error();}
        catch(e){input.setCustomValidity('Enter a valid HTTPS link.');input.reportValidity();input.setCustomValidity('');return;}
        const f=document.createElement('form');f.method='POST';f.setAttribute('action', form.getAttribute('action'));f.target='_blank';f.style.display='none';
        const fields={action:'view_endorsement_link',csrf_token:form.elements['csrf_token'].value,endorsement_document_url:url};
        for(const key in fields){const h=document.createElement('input');h.type='hidden';h.name=key;h.value=fields[key];f.appendChild(h);}
        document.body.appendChild(f);f.submit();f.remove();viewed=url;submit.disabled=false;
    });
    form.addEventListener('submit',function(ev){if(!viewed||viewed!==input.value.trim()){
        ev.preventDefault();submit.disabled=true;alert('You must click the eye button to view the link before submitting.');
    }});
})();
</script>
<?php endif; ?>

<?php if (in_array((string) $program['status'], ['Approved', 'Ongoing', 'Completed'], true)): ?>
<!-- Approved proposal: official documents issued by ESO. -->
<section class="extension-panel extension-official-documents">
    <div class="extension-panel-header">
        <h2 class="extension-panel-title">
            <span class="extension-panel-icon"><i class="bi bi-patch-check"></i></span>
            Official Notice and Certification
        </h2>
    </div>
    <div class="extension-panel-body">
        <?php
            // Older records may have stored Notice of Approval as the single
            // final certification. Keep those links viewable for compatibility.
            $legacyNotice = !$issuedNotice && $officialCertification
                && (string) $officialCertification['document_type'] === 'Notice of Approval'
                ? $officialCertification : null;
            $noticeHref = $issuedNotice['notice_url'] ?? ($legacyNotice['document_url'] ?? '');
        ?>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <strong class="d-block">Notice of Approval</strong>
                <small class="text-muted">
                    <?php if ($issuedNotice): ?>
                        Issued <?= e($issuedNotice['notice_date']); ?>
                    <?php elseif ($legacyNotice): ?>
                        Recorded <?= e($legacyNotice['certification_date']); ?>
                    <?php else: ?>
                        Not yet issued by the Extension Service Office
                    <?php endif; ?>
                </small>
            </div>
            <?php if ($noticeHref !== ''): ?>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e($noticeHref); ?>" target="_blank" rel="noopener noreferrer"
                       class="btn btn-sm btn-outline-asu" title="View official Notice of Approval">
                        <i class="bi bi-eye me-1"></i> View Notice
                    </a>
                    <a href="<?= e($noticeHref); ?>" target="_blank" rel="noopener noreferrer"
                       class="btn btn-sm btn-outline-asu" download title="Open notice to download from its document provider">
                        <i class="bi bi-download me-1"></i> Download Notice
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($officialCertification && (string) $officialCertification['document_type'] === 'BOR Certification'): ?>
        <hr>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <strong class="d-block">BOR Certification</strong>
                <small class="text-muted">Recorded <?= e($officialCertification['certification_date']); ?></small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= e($officialCertification['document_url']); ?>" target="_blank" rel="noopener noreferrer"
                   class="btn btn-sm btn-outline-asu" title="View official BOR Certification">
                    <i class="bi bi-eye me-1"></i> View Certification
                </a>
                <a href="<?= e($officialCertification['document_url']); ?>" target="_blank" rel="noopener noreferrer"
                   class="btn btn-sm btn-outline-asu" download title="Open certification to download from its document provider">
                    <i class="bi bi-download me-1"></i> Download Certification
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($officialBorNotation): ?>
            <hr>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <strong class="d-block">Official LADCO/UADCO/BOR</strong>
                    <small class="text-muted">Recorded <?= e($officialBorNotation['decision_date']); ?></small>
                </div>
                <a href="<?= e($officialBorNotation['document_url']); ?>" target="_blank" rel="noopener noreferrer"
                   class="btn btn-sm btn-outline-asu" title="View LADCO/UADCO/BOR supporting document">
                    <i class="bi bi-eye me-1"></i> View LADCO/UADCO/BOR
                </a>
            </div>
        <?php endif; ?>

        <p class="small text-muted mt-3 mb-0">For cloud-hosted documents, use the Download option in the opened document if your browser opens it instead of saving it.</p>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     MAIN GRID
====================================================== -->

<div class="extension-grid">


<!-- =====================================================
     LEFT
====================================================== -->

<div class="extension-column">


<!-- PROGRAM INFORMATION -->

<section class="extension-panel">


<div class="extension-panel-header">


<h2 class="extension-panel-title">


<span class="extension-panel-icon">

<i class="bi bi-megaphone"></i>

</span>

Program/Project Information

</h2>


</div>


<div class="extension-panel-body">


<div class="info-grid">


<div class="info-item">

<label>
Program/Project Category
</label>

<strong>

<?= e(
    $program[
        'program_category'
    ]
    ?: 'Not specified'
); ?>

</strong>

</div>


<div class="info-item">

<label>
Program/Project Type
</label>

<strong>

<?= e(
    $program[
        'program_type'
    ]
    ?: 'Not specified'
); ?>

</strong>

</div>


<div class="info-item">

<label>
Campus
</label>

<strong>

<?= e(
    $program[
        'campus'
    ]
    ?: 'Not assigned'
); ?>

</strong>

</div>


<div class="info-item">

<label>
College/Department
</label>

<strong>

<?= e(
    ($program['college_name'] ?? '') !== ''
        ? ($program['college_name'] . (
            !empty($program['department_name']) ? ' / ' . $program['department_name'] : ''
        ))
        : 'Not assigned'
); ?>

</strong>

</div>


<div class="info-item">

<label>
Target Community / Barangay
</label>

<strong>

<?= e(
    $program[
        'target_community'
    ]
); ?>

</strong>

</div>


<div class="info-item">

<label>
Municipality
</label>

<strong>

<?= e(
    $program[
        'municipality'
    ]
    ?: 'Not specified'
); ?>

</strong>

</div>


<div class="info-item">

<label>
Province
</label>

<strong>

<?= e(
    $program[
        'province'
    ]
    ?: 'Not specified'
); ?>

</strong>

</div>


<div class="info-item">

<label>
Target Participants
</label>

<strong>

<?php if (
    $program[
        'target_participants'
    ]
    !== null
): ?>

<?= number_format(
    (int)$program[
        'target_participants'
    ]
); ?>

<?php else: ?>

Not specified

<?php endif; ?>

</strong>

</div>


</div>


</div>


</section>



<!-- PROGRAM CONTENT -->

<section class="extension-panel">


<div class="extension-panel-header">


<h2 class="extension-panel-title">


<span class="extension-panel-icon">

<i class="bi bi-file-text"></i>

</span>

Program/Project Content

</h2>


</div>


<div class="extension-panel-body">


<div class="content-block">

<h4>
Objectives
</h4>

<p><?= e(
    $program[
        'objectives'
    ]
); ?></p>

</div>


<div class="content-block">

<h4>
Program/Project Description
</h4>

<p><?= e(
    $program[
        'program_description'
    ]
); ?></p>

</div>


<div class="content-block">

<h4>
Expected Output
</h4>

<p><?= e(
    $program[
        'expected_output'
    ]
); ?></p>

</div>


</div>


</section>



<!-- FUNDING / SCHEDULE -->

<section class="extension-panel">


<div class="extension-panel-header">


<h2 class="extension-panel-title">


<span class="extension-panel-icon">

<i class="bi bi-calendar3"></i>

</span>

Funding & Schedule

</h2>


</div>


<div class="extension-panel-body">


<div class="info-grid">


<div class="info-item">

<label>
Funding Source
</label>

<strong>

<?= e(
    $program[
        'funding_source'
    ]
    ?: 'Not specified'
); ?>

</strong>

</div>


<div class="info-item">

<label>
Estimated Budget
</label>

<strong>

<?php if (
    $program[
        'estimated_budget'
    ]
    !== null
): ?>

₱<?= number_format(
    (float)$program[
        'estimated_budget'
    ],
    2
); ?>

<?php else: ?>

Not specified

<?php endif; ?>

</strong>

</div>


<div class="info-item">

<label>
Start Date
</label>

<strong>

<?php if (
    !empty(
        $program[
            'start_date'
        ]
    )
): ?>

<?= e(
    date(
        'F d, Y',
        strtotime(
            $program[
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


<div class="info-item">

<label>
End Date
</label>

<strong>

<?php if (
    !empty(
        $program[
            'end_date'
        ]
    )
): ?>

<?= e(
    date(
        'F d, Y',
        strtotime(
            $program[
                'end_date'
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


</div>



<!-- =====================================================
     RIGHT
====================================================== -->

<div class="extension-column">


<!-- COORDINATOR -->

<section class="extension-panel">


<div class="extension-panel-header">


<h2 class="extension-panel-title">


<span class="extension-panel-icon">

<i class="bi bi-person-badge"></i>

</span>

Program/Project Coordinator

</h2>


</div>


<div class="extension-panel-body">

<div class="extension-coordinator-layout">
<div class="extension-coordinator-details">


<strong
    style="
        display:block;
        margin-bottom:4px;
        font-size:11px;
    "
>

<?= e(
    $coordinatorName
); ?>

</strong>


<span
    style="
        display:block;
        color:#818982;
        font-size:9px;
    "
>

<?= e(
    $program[
        'email'
    ]
); ?>

</span>


<span
    style="
        display:block;
        margin-top:3px;
        color:#818982;
        font-size:9px;
    "
>

Extension Coordinator

</span>

</div><!-- /coordinator details -->

<div class="extension-view-proponents" aria-labelledby="extensionViewProponentsLabel">
    <div class="extension-view-proponents-header" id="extensionViewProponentsLabel">
        <span><i class="bi bi-people-fill" aria-hidden="true"></i> Proponents</span>
        <small>Encoded by coordinator</small>
    </div>

    <?php if ($programProponentsLoadError): ?>
        <p class="extension-view-proponents-message">
            Unable to load proponents. Check the extension proponents database table.
        </p>
    <?php elseif ($programProponents): ?>
        <ol class="extension-view-proponents-list">
            <?php foreach ($programProponents as $proponentFullName): ?>
                <li><?= e($proponentFullName); ?></li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <p class="extension-view-proponents-message">No proponents added for this proposal.</p>
    <?php endif; ?>
</div><!-- /proponents -->

</div><!-- /coordinator two-column layout -->

</div>


</section>



</div>

</div>


<!-- =====================================================
     OFFICIAL CALL REFERENCE
====================================================== -->
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>Call for Proposals</h4>
            <?php if ($extensionCall): ?>
                <p>
                    <?= e($extensionCall['memo_title']); ?>
                    <?php if (!empty($extensionCall['memo_number'])): ?>
                        (<?= e($extensionCall['memo_number']); ?>)
                    <?php endif; ?>
                    — <?= e($extensionCall['opens_on']); ?> to <?= e($extensionCall['closes_on']); ?>
                </p>
                <?php if ($program['status'] === 'Draft' && !$callAcceptsSubmissions): ?>
                    <div class="workflow-notice">
                        The linked call is not accepting submissions. Contact the Extension Service Office.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>No official Call for Proposals is linked to this record.</p>
                <?php if ($program['status'] === 'Draft'): ?>
                    <div class="workflow-notice">
                        This draft must be linked to an active call before it can be submitted.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($extensionCall): ?>
            <div class="workflow-buttons">
                <a href="<?= e($extensionCall['memo_url']); ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="btn btn-outline-asu" title="View Official Memo"
                   aria-label="View Official Memo">
                    <i class="bi bi-eye"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- =====================================================
     COORDINATOR DRAFT ACTIONS
====================================================== -->

<?php if (
    extensionIsCoordinator()
    &&
    $program['status']
    === 'Draft'
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Draft Extension Program/Project

</h4>


<p>

Review the program/project information and supporting
document before submitting the extension program/project
to the Extension Service Office.

</p>


<?php if (
    !$hasProgramProposalDocument
): ?>


<div class="workflow-notice">

A Program/Project Proposal HTTPS link is required before submission.
Use Edit Program/Project to add your link, then click the eye button to view it.

</div>


<?php endif; ?>


</div>


<div class="workflow-buttons">


<?php if (
    $editPageExists
): ?>


<a
    href="<?= BASE_URL; ?>/extension/edit.php?id=<?= $extensionId; ?>"
    class="btn btn-outline-asu"
>

<i class="bi bi-pencil-square me-1"></i>

Edit Program/Project

</a>


<?php endif; ?>


<button
    type="button"
    class="btn btn-asu"
    data-bs-toggle="modal"
    data-bs-target="#submitProgramModal"
    <?= (!$hasProgramProposalDocument || !$callAcceptsSubmissions)
        ? 'disabled'
        : ''; ?>
>

<i class="bi bi-send me-1"></i>

Submit for Review

</button>


</div>


</div>


</section>


<?php endif; ?>



<!-- =====================================================
     SUBMITTED NOTICE
====================================================== -->

<?php if (
    extensionIsCoordinator()
    &&
    in_array(
        $program['status'],
        [
            'Submitted',
            'Under Review'
        ],
        true
    )
): ?>


<div class="design-notice">

<strong>
Proposal Submitted:
</strong>

This extension program/project is currently within
the Extension Service Office workflow.

</div>


<?php endif; ?>



<!-- =====================================================
     ADMIN SUBMITTED NOTICE
====================================================== -->

<?php if (
    extensionIsOffice()
    &&
    $program['status']
    === 'Submitted'
): ?>


<div class="design-notice">

<strong>
Submitted Proposal:
</strong>

This extension program/project has been formally submitted.
The ESO/UERC review actions will be
connected in the next Extension workflow step.

</div>


<?php endif; ?>
<?php endif; ?>


</div>


</main>


</div>



<!-- =========================================================
     SUBMIT PROGRAM MODAL
========================================================= -->

<?php if (
    extensionIsCoordinator()
    &&
    $program['status']
    === 'Draft'
    &&
    $hasProgramProposalDocument
    && $callAcceptsSubmissions
): ?>


<div
    class="modal fade"
    id="submitProgramModal"
    tabindex="-1"
>


<div
    class="
        modal-dialog
        modal-dialog-centered
    "
>


<div class="modal-content">


<div class="modal-header">


<h5 class="modal-title">

Submit Extension Program/Project

</h5>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>


</div>


<form method="POST">


<div class="modal-body">


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(
        generateCsrfToken()
    ); ?>"
>


<input
    type="hidden"
    name="action"
    value="submit_for_review"
>


<p>

Submit this extension program/project
for administrative review?

</p>


<div class="alert alert-warning mb-0">

After submission, this proposal will no longer
be a private Draft. The Extension Service Office
will receive it for UERC processing.

</div>


</div>


<div class="modal-footer">


<button
    type="button"
    class="btn btn-outline-secondary"
    data-bs-dismiss="modal"
>

Cancel

</button>


<button
    type="submit"
    class="btn btn-asu"
>

<i class="bi bi-send me-1"></i>

Confirm Submission

</button>


</div>


</form>


</div>


</div>


</div>


<?php endif; ?>


<?php

require __DIR__ .
    '/../includes/footer.php';

?>
