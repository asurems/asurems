<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/research_revision_deadlines.php';


/**
 * Identify the active revision round without inventing an RPS evaluation.
 * Some older test records have a valid workflow decision / history row but
 * lack a matching research_evaluations record. The decision's history_id is
 * therefore the preferred stable round key. A workflow timestamp is a final
 * compatibility fallback for records where neither audit table is populated.
 *
 * Crucially, Save Revision does not change workflow_updated_at, so the key
 * stays stable until the faculty submits. The next RPS revision creates a new
 * history ID / timestamp and resets the Submit Revision gate.
 */
function remsRevisionRoundIdentity(PDO $pdo, int $researchId, string $stage): ?array
{
    if (!in_array($stage, ['URRC', 'INHOUSE'], true)) {
        return null;
    }

    $rowStmt = $pdo->prepare('SELECT status, workflow_status, is_archived, workflow_updated_at
        FROM research_proposals WHERE research_id = ? LIMIT 1');
    $rowStmt->execute([$researchId]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
    $required = $stage === 'URRC' ? 'URRC_REVISION_REQUIRED' : 'INHOUSE_REVISION_REQUIRED';
    if (!$row || (string)$row['workflow_status'] !== $required
        || (string)$row['status'] !== 'For Revision'
        || (int)$row['is_archived'] !== 0) {
        return null;
    }

    $historyPrefix = $stage === 'URRC'
        ? 'URRC result recorded: Accepted with Revision%'
        : 'In-House result recorded: Passed with Revision%';
    $historyStmt = $pdo->prepare("SELECT history_id, changed_at
        FROM research_status_history
        WHERE research_id = ? AND new_status = 'For Revision' AND remarks LIKE ?
        ORDER BY history_id DESC LIMIT 1");
    $historyStmt->execute([$researchId, $historyPrefix]);
    $history = $historyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$history) {
        // Earlier versions sometimes used a different review remark phrase.
        // Exclude faculty saves, which are NOT new review decisions.
        $stageLike = $stage === 'URRC' ? '%URRC%' : '%In-House%';
        $historyStmt = $pdo->prepare("SELECT history_id, changed_at
            FROM research_status_history
            WHERE research_id = ? AND new_status = 'For Revision'
              AND remarks LIKE ? AND remarks NOT LIKE 'Faculty %'
            ORDER BY history_id DESC LIMIT 1");
        $historyStmt->execute([$researchId, $stageLike]);
        $history = $historyStmt->fetch(PDO::FETCH_ASSOC);
    }

    $evaluationDecision = $stage === 'URRC'
        ? 'URRC Accepted with Revision' : 'In-House Passed with Revision';
    $evalStmt = $pdo->prepare('SELECT evaluation_id, evaluated_at FROM research_evaluations
        WHERE research_id = ? AND decision = ? ORDER BY evaluation_id DESC LIMIT 1');
    $evalStmt->execute([$researchId, $evaluationDecision]);
    $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC);

    $historyId = $history ? (int)$history['history_id'] : 0;
    $evaluationId = $evaluation ? (int)$evaluation['evaluation_id'] : 0;
    $historyAt = $history ? (string)$history['changed_at'] : '';
    $evaluationAt = $evaluation ? (string)$evaluation['evaluated_at'] : '';
    // Only allow old evaluation-based document names if the evaluation
    // belongs to this decision, not a previous revision round.
    $legacyEvaluationId = 0;
    if ($evaluationId > 0) {
        if (!$historyId || ($historyAt !== '' && $evaluationAt !== ''
            && abs(strtotime($evaluationAt) - strtotime($historyAt)) <= 5)) {
            $legacyEvaluationId = $evaluationId;
        }
    }

    if ($historyId > 0) {
        $key = 'h' . $historyId;
    } elseif ($evaluationId > 0) {
        $key = 'e' . $evaluationId;
    } elseif (!empty($row['workflow_updated_at'])) {
        // A deterministic key scoped to the date the RPS changed the stage.
        $key = 't' . substr(hash('sha256', $stage . '|' . $row['workflow_updated_at']), 0, 12);
    } else {
        return null;
    }

    $code = $stage === 'URRC' ? 'rvu' : 'rvi';
    $prefixes = [$code . $key . '_r' . $researchId . '_'];
    if ($legacyEvaluationId > 0) {
        $prefixes[] = $code . $legacyEvaluationId . '_r' . $researchId . '_';
        $legacyStage = $stage === 'URRC' ? 'urrc' : 'inhouse';
        $prefixes[] = 'linked_' . $legacyStage . '_revision_e' . $legacyEvaluationId
            . '_' . $researchId . '_';
    }

    return [
        'key' => $key,
        'prefix' => $prefixes[0],
        'prefixes' => $prefixes,
        'history_at' => $historyAt,
        'legacy_evaluation_id' => $legacyEvaluationId,
    ];
}

/** Guard against an old browser tab editing an already finalized round. */
function remsRevisionRoundWasSubmitted(PDO $pdo, int $researchId, string $stage, array $round): bool
{
    $label = $stage === 'URRC'
        ? 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.'
        : 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.';
    $stmt = $pdo->prepare("SELECT remarks FROM research_status_history
        WHERE research_id = ? AND old_status = 'For Revision'
          AND new_status = 'Submitted' AND remarks LIKE ?
        ORDER BY history_id DESC LIMIT 1");
    $stmt->execute([$researchId, $label . '%']);
    $historyText = $stmt->fetchColumn();
    if (!is_string($historyText) || $historyText === '') {
        return false;
    }
    if (preg_match('/\[REVISION_DOC_ID=[0-9]+;ROUND=(h[0-9]+|e[0-9]+|t[a-f0-9]{12})\]/i',
        $historyText, $matches)) {
        return strtolower($matches[1]) === strtolower($round['key']);
    }
    // Legacy audit entries have no round key. Compare their row ID against
    // the decision row ID so a previous round won't lock the newly requested one.
    $stmt = $pdo->prepare("SELECT history_id FROM research_status_history
        WHERE research_id = ? AND old_status = 'For Revision'
          AND new_status = 'Submitted' AND remarks LIKE ?
        ORDER BY history_id DESC LIMIT 1");
    $stmt->execute([$researchId, $label . '%']);
    $submissionId = (int)$stmt->fetchColumn();
    return str_starts_with((string)$round['key'], 'h')
        && $submissionId > (int)substr((string)$round['key'], 1);
}

requireLogin();


/*
|--------------------------------------------------------------------------
| FACULTY ONLY
|--------------------------------------------------------------------------
*/

if (
    ($_SESSION['role'] ?? '') !== 'faculty'
) {

    redirect(
        BASE_URL . '/research/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$user = currentUser();

$pageTitle = 'Edit Research Proposal';
$activePage = 'research';

$errors = [];


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
        'Invalid research proposal.'
    );

    redirect(
        BASE_URL . '/research/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD RESEARCH RECORD
|--------------------------------------------------------------------------
*/

// Protect against a stale edit form being submitted after day 21.
// An overdue proposal becomes read-only before its editability is checked.
remsArchiveExpiredUrrcRevisions($pdo, $researchId);
remsArchiveExpiredInhouseRevisions($pdo, $researchId);

$stmt = $pdo->prepare("
    SELECT

        rp.research_id,
        rp.researcher_id,

        rp.research_title,
        rp.research_category,
        rp.research_type,

        rp.abstract,
        rp.objectives,
        rp.methodology,
        rp.expected_output,

        rp.funding_source,
        rp.estimated_budget,

        rp.start_date,
        rp.target_completion_date,

        rp.status,
        rp.workflow_status,
        rp.is_archived,

        rp.college_id,
        rp.department_id,

        c.college_name,
        d.department_name

    FROM research_proposals rp

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


/*
|--------------------------------------------------------------------------
| NOT FOUND
|--------------------------------------------------------------------------
*/

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
| OWNERSHIP CHECK
|--------------------------------------------------------------------------
*/

if (
    (int)$research['researcher_id']
    !==
    (int)$user['user_id']
) {

    setFlash(
        'danger',
        'You are not authorized to edit this research proposal.'
    );

    redirect(
        BASE_URL . '/research/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| EDITABLE STATUS
|--------------------------------------------------------------------------
|
| Faculty may edit:
|
| Draft
| For Revision
|
*/

$editableStatuses = [
    'Draft',
    'For Revision'
];

// Both URRC and In-House revisions use an explicit two-step process:
// Save Revision in this form, then Submit Revision from the details page.
// Both actions are scoped to the current review round, including legacy history-only records.
$isUrrcRevisionEdit =
    ($research['status'] ?? '') === 'For Revision'
    && ($research['workflow_status'] ?? '') === 'URRC_REVISION_REQUIRED'
    && (int)($research['is_archived'] ?? 0) === 0;
$isInhouseRevisionEdit =
    ($research['status'] ?? '') === 'For Revision'
    && ($research['workflow_status'] ?? '') === 'INHOUSE_REVISION_REQUIRED'
    && (int)($research['is_archived'] ?? 0) === 0;
$isDirectRevisionEdit = false; // Save only; final submission is on view.php.


if (
    !in_array(
        $research['status'],
        $editableStatuses,
        true
    )
) {

    setFlash(
        'warning',
        'This research proposal cannot be edited while it is currently under the review workflow.'
    );

    redirect(
        BASE_URL .
        '/research/view.php?id=' .
        $researchId
    );
}


/*
|--------------------------------------------------------------------------
| LOAD LATEST ADMIN REVISION REMARK
|--------------------------------------------------------------------------
*/

$revisionRemark = null;

// Show the remarks from the active review stage, not an unrelated old evaluation.
$revisionDecision = match ((string)($research['workflow_status'] ?? '')) {
    'URRC_REVISION_REQUIRED' => 'URRC Accepted with Revision',
    'INHOUSE_REVISION_REQUIRED' => 'In-House Passed with Revision',
    default => 'For Revision'
};

if (
    $research['status']
    === 'For Revision'
) {

    $remarkStmt =
        $pdo->prepare("
            SELECT
                re.remarks,
                re.evaluated_at,

                u.first_name,
                u.middle_name,
                u.last_name

            FROM research_evaluations re

            INNER JOIN users u
                ON u.user_id = re.evaluator_id

            WHERE re.research_id = ?
              AND re.decision = ?

            ORDER BY
                re.evaluated_at DESC,
                re.evaluation_id DESC

            LIMIT 1
        ");


    $remarkStmt->execute([
        $researchId,
        $revisionDecision
    ]);


    $revisionRemark =
        $remarkStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$revisionRemark && ($isUrrcRevisionEdit || $isInhouseRevisionEdit)) {
        // Older records may have a genuine RPS decision in status history,
        // even without a corresponding research_evaluations row.
        $stagePattern = $isUrrcRevisionEdit
            ? 'URRC result recorded: Accepted with Revision%'
            : 'In-House result recorded: Passed with Revision%';
        $historyRemarkStmt = $pdo->prepare("SELECT h.remarks, h.changed_at AS evaluated_at,
                u.first_name, u.middle_name, u.last_name
            FROM research_status_history h
            INNER JOIN users u ON u.user_id = h.changed_by
            WHERE h.research_id = ? AND h.new_status = 'For Revision'
              AND h.remarks LIKE ?
            ORDER BY h.history_id DESC LIMIT 1");
        $historyRemarkStmt->execute([$researchId, $stagePattern]);
        $revisionRemark = $historyRemarkStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}


/*
|--------------------------------------------------------------------------
| REVISED PROPOSAL LINK
|--------------------------------------------------------------------------
| Draft: optional replacement document link.
| For Revision: a revised proposal link is required before Save Revision.
*/

$revisedDocumentLink = '';


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$researchTitle =
    $research['research_title'];

$researchCategory =
    $research['research_category'];

$researchType =
    $research['research_type'];

$abstract =
    $research['abstract'];

$objectives =
    $research['objectives'];

$methodology =
    $research['methodology'];

$expectedOutput =
    $research['expected_output'];

$fundingSource =
    $research['funding_source'] ?? '';

$estimatedBudget =
    $research['estimated_budget'] ?? '';

$startDate =
    $research['start_date'] ?? '';

$targetCompletionDate =
    $research['target_completion_date'] ?? '';


/*
|--------------------------------------------------------------------------
| REVISED DOCUMENT — RECORD EYE-BUTTON LINK VIEW
|--------------------------------------------------------------------------
| Only the owner of an editable proposal reaches this endpoint. A new or
| changed link must be opened again before the edited proposal can be saved.
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'confirm_revised_document_link_view'
) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid request. Refresh the page and try again.']);
        exit;
    }

    $linkToView = trim((string)($_POST['revised_document_link'] ?? ''));
    $viewUrlParts = parse_url($linkToView);
    if (
        $linkToView === ''
        || strlen($linkToView) > 255
        || !filter_var($linkToView, FILTER_VALIDATE_URL)
        || !is_array($viewUrlParts)
        || strtolower((string)($viewUrlParts['scheme'] ?? '')) !== 'https'
        || empty($viewUrlParts['host'])
        || isset($viewUrlParts['user'])
        || isset($viewUrlParts['pass'])
    ) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Enter a valid HTTPS document link first.']);
        exit;
    }

    $viewSessionKey = 'edit:' . $researchId . ':' . (int)$user['user_id'];
    $_SESSION['rems_edit_proposal_link_views'][$viewSessionKey] = hash('sha256', $linkToView);
    echo json_encode(['success' => true]);
    exit;
}

/*
|--------------------------------------------------------------------------
| HANDLE UPDATE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {


    /*
    |--------------------------------------------------------------------------
    | CSRF
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
    | GET VALUES
    |--------------------------------------------------------------------------
    */

    $researchTitle =
        trim(
            $_POST['research_title']
            ?? ''
        );


    $researchCategory =
        trim(
            $_POST['research_category']
            ?? ''
        );


    $researchType =
        trim(
            $_POST['research_type']
            ?? ''
        );


    $abstract =
        trim(
            $_POST['abstract']
            ?? ''
        );


    $objectives =
        trim(
            $_POST['objectives']
            ?? ''
        );


    $methodology =
        trim(
            $_POST['methodology']
            ?? ''
        );


    $expectedOutput =
        trim(
            $_POST['expected_output']
            ?? ''
        );


    $fundingSource =
        trim(
            $_POST['funding_source']
            ?? ''
        );


    $estimatedBudget =
        trim(
            $_POST['estimated_budget']
            ?? ''
        );


    $startDate =
        trim(
            $_POST['start_date']
            ?? ''
        );


    $targetCompletionDate =
        trim(
            $_POST['target_completion_date']
            ?? ''
        );


    // Link-only document input (replaces revised_file upload).
    $revisedDocumentLink = trim((string)($_POST['revised_document_link'] ?? ''));


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($researchTitle === '') {

        $errors[] =
            'Research title is required.';
    }


    if (
        mb_strlen(
            $researchTitle
        ) > 255
    ) {

        $errors[] =
            'Research title must not exceed 255 characters.';
    }


    if ($researchCategory === '') {

        $errors[] =
            'Research category is required.';
    }


    if ($researchType === '') {

        $errors[] =
            'Research type is required.';
    }


    if ($abstract === '') {

        $errors[] =
            'Research abstract is required.';
    }


    if ($objectives === '') {

        $errors[] =
            'Research objectives are required.';
    }


    if ($methodology === '') {

        $errors[] =
            'Research methodology is required.';
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
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $startDate !== ''
        &&
        $targetCompletionDate !== ''
    ) {

        $startTimestamp =
            strtotime(
                $startDate
            );

        $completionTimestamp =
            strtotime(
                $targetCompletionDate
            );


        if (
            $startTimestamp === false
            ||
            $completionTimestamp === false
        ) {

            $errors[] =
                'Please enter valid research dates.';

        } elseif (
            $completionTimestamp
            <
            $startTimestamp
        ) {

            $errors[] =
                'Target completion date cannot be earlier than the proposed start date.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REVISED PROPOSAL LINK VALIDATION
    |--------------------------------------------------------------------------
    | Only an HTTPS document link can be submitted. Same requirement as
    | the original file upload: For Revision = required, Draft = optional.
    */

    if ($research['status'] === 'For Revision' && $revisedDocumentLink === '') {
        $errors[] = 'Please provide the revised research proposal document link.';
    }

    if ($revisedDocumentLink !== '') {
        $parts = parse_url($revisedDocumentLink);
        if (
            strlen($revisedDocumentLink) > 255
            || !filter_var($revisedDocumentLink, FILTER_VALIDATE_URL)
            || !is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            $errors[] = 'Enter a valid HTTPS revised proposal document link (maximum 255 characters).';
        }

        // Require an authenticated eye-button visit for this exact link.
        // A hidden input alone cannot bypass this server-side check.
        $viewSessionKey = 'edit:' . $researchId . ':' . (int)$user['user_id'];
        $viewHash = $_SESSION['rems_edit_proposal_link_views'][$viewSessionKey] ?? null;
        if (!is_string($viewHash)
            || !hash_equals($viewHash, hash('sha256', $revisedDocumentLink))) {
            $errors[] = 'You must click the eye button to view the document link before saving.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE UPDATE
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {


            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | RECHECK CURRENT DATABASE STATUS
            |--------------------------------------------------------------------------
            |
            | Prevent editing if status changed in another session.
            |
            */

            $checkStmt =
                $pdo->prepare("
                    SELECT
                        researcher_id,
                        status,
                        workflow_status,
                        is_archived
                    FROM research_proposals
                    WHERE research_id = ?
                    FOR UPDATE
                ");


            $checkStmt->execute([
                $researchId
            ]);


            $currentRecord =
                $checkStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$currentRecord
                ||
                (int)$currentRecord[
                    'researcher_id'
                ]
                !==
                (int)$user[
                    'user_id'
                ]
            ) {

                throw new RuntimeException(
                    'Research record authorization failed.'
                );
            }


            if (
                !in_array(
                    $currentRecord[
                        'status'
                    ],
                    $editableStatuses,
                    true
                )
            ) {

                throw new RuntimeException(
                    'Research record is no longer editable.'
                );
            }


            // Re-check status under the database lock to avoid stale submissions.
            $submitUrrcRevision =
                $currentRecord['status'] === 'For Revision'
                && ($currentRecord['workflow_status'] ?? '') === 'URRC_REVISION_REQUIRED'
                && (int)($currentRecord['is_archived'] ?? 0) === 0;
            $submitInhouseRevision =
                $currentRecord['status'] === 'For Revision'
                && ($currentRecord['workflow_status'] ?? '') === 'INHOUSE_REVISION_REQUIRED'
                && (int)($currentRecord['is_archived'] ?? 0) === 0;
            $submitReviewRevision = false; // Neither Save Revision automatically submits.

            // The automatic archive routines are invoked above before editing.
            // Do not accept a submission whose revision window has elapsed.
            if ((int)($currentRecord['is_archived'] ?? 0) !== 0) {
                throw new RuntimeException('Archived research is not editable.');
            }
            if ($submitUrrcRevision) {
                $urrcDeadline = remsUrrcRevisionDeadline($pdo, $researchId);
                if ($urrcDeadline && (int)($urrcDeadline['expired'] ?? 0) === 1) {
                    throw new RuntimeException('URRC revision deadline expired before submission.');
                }
            }
            // Use the current review round, not an exact evaluation label.
            // Old records with missing research_evaluations rows can still be
            // saved, without falsifying a reviewer decision.
            $urrcRevisionRound = $submitUrrcRevision
                ? remsRevisionRoundIdentity($pdo, $researchId, 'URRC') : null;
            $inhouseRevisionRound = $submitInhouseRevision
                ? remsRevisionRoundIdentity($pdo, $researchId, 'INHOUSE') : null;
            if ($submitUrrcRevision && $urrcRevisionRound === null) {
                throw new RuntimeException('Unable to establish the current URRC revision round.');
            }
            if ($submitInhouseRevision) {
                $inhouseDeadline = remsInhouseRevisionDeadline($pdo, $researchId);
                if (!$inhouseDeadline || (int)($inhouseDeadline['expired'] ?? 0) === 1) {
                    throw new RuntimeException('In-House revision deadline is missing or expired.');
                }
                if ($inhouseRevisionRound === null) {
                    throw new RuntimeException('Unable to establish the current In-House revision round.');
                }
            }
            if ($submitUrrcRevision && remsRevisionRoundWasSubmitted($pdo, $researchId, 'URRC', $urrcRevisionRound)) {
                throw new RuntimeException('This URRC revision was already submitted and can no longer be edited.');
            }
            if ($submitInhouseRevision && remsRevisionRoundWasSubmitted($pdo, $researchId, 'INHOUSE', $inhouseRevisionRound)) {
                throw new RuntimeException('This In-House revision was already submitted and can no longer be edited.');
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE PROPOSAL
            |--------------------------------------------------------------------------
            */

            $updateStmt =
                $pdo->prepare("
                    UPDATE research_proposals

                    SET
                        research_title = ?,
                        research_category = ?,
                        research_type = ?,

                        abstract = ?,
                        objectives = ?,
                        methodology = ?,
                        expected_output = ?,

                        funding_source = ?,
                        estimated_budget = ?,

                        start_date = ?,
                        target_completion_date = ?

                    WHERE research_id = ?
                      AND researcher_id = ?
                ");


            $updateStmt->execute([

                $researchTitle,

                $researchCategory,

                $researchType,

                $abstract,

                $objectives,

                $methodology,

                $expectedOutput,


                $fundingSource !== ''
                    ? $fundingSource
                    : null,


                $budgetValue,


                $startDate !== ''
                    ? $startDate
                    : null,


                $targetCompletionDate !== ''
                    ? $targetCompletionDate
                    : null,


                $researchId,

                (int)$user[
                    'user_id'
                ]

            ]);


            /*
            |--------------------------------------------------------------------------
            | SAVE REVISED PROPOSAL DOCUMENT LINK
            |--------------------------------------------------------------------------
            | File upload replaced with an HTTPS URL stored in the same
            | research_documents table. Older physical files remain intact.
            | The existing document types and workflow statuses are retained.
            */

            if ($revisedDocumentLink !== '') {

                $documentType =
                    $currentRecord['status'] === 'For Revision'
                        ? 'Revised Proposal'
                        : 'Proposal';

                $documentLabel =
                    $currentRecord['status'] === 'For Revision'
                        ? 'Revised Proposal Link'
                        : 'Proposal Link';

                // Keep the existing 'Revised Proposal' document type.
                // Scope each saved revision to its current URRC/In-House decision,
                // so a previous review round never unlocks Submit Revision.
                // Use SHORT, stage- and evaluation-specific names. Some
                // installations have a short research_documents.stored_name
                // column: a verbose name can fail with SQLSTATE 22001 and
                // roll back the entire revision. The random portion retains
                // uniqueness and the evaluation ID resets the submit gate
                // for every new revision round.
                $storedName = $submitUrrcRevision
                    ? $urrcRevisionRound['prefix'] . bin2hex(random_bytes(6))
                    : ($submitInhouseRevision
                        ? $inhouseRevisionRound['prefix'] . bin2hex(random_bytes(6))
                        : 'linked_revision_' . $researchId . '_'
                            . date('YmdHis') . '_' . bin2hex(random_bytes(6)));

                $documentStmt = $pdo->prepare("
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
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $documentStmt->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $documentType,
                    $documentLabel,
                    $storedName,
                    $revisedDocumentLink,
                    'url',
                    0
                ]);
            }


            // Save Revision intentionally leaves status = For Revision.
            // The Faculty must explicitly click Submit Revision on view.php.

            /*
            |--------------------------------------------------------------------------
            | HISTORY
            |--------------------------------------------------------------------------
            | URRC/In-House revisions advance into their respective RPS queues.
            | Other edits preserve their existing workflow and resubmit controls.
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
                        ?,
                        ?,
                        ?
                    )
                ");


            if (
                $currentRecord[
                    'status'
                ]
                === 'For Revision'
            ) {

                $historyRemarks = $submitUrrcRevision
                    ? 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.'
                    : ($submitInhouseRevision
                        ? 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.'
                        : 'Faculty revised the research proposal and provided a revised proposal document link.');

            } else {

                $historyRemarks =
                    $revisedDocumentLink !== ''
                        ? 'Faculty updated the draft research proposal and provided a proposal document link.'
                        : 'Faculty updated the draft research proposal.';
            }


            $historyStmt->execute([

                $researchId,

                (int)$user[
                    'user_id'
                ],

                $currentRecord[
                    'status'
                ],

                $submitReviewRevision ? 'Submitted' : $currentRecord['status'],

                $historyRemarks

            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            // A saved document link needs a fresh eye-button click on its next edit.
            if ($revisedDocumentLink !== '') {
                $viewSessionKey = 'edit:' . $researchId . ':' . (int)$user['user_id'];
                unset($_SESSION['rems_edit_proposal_link_views'][$viewSessionKey]);
            }

            // Notifications are sent after commit, so a notification error cannot
            // roll back an otherwise successful submission.
            if ($submitReviewRevision) {
                $reviewStageName = $submitUrrcRevision ? 'URRC' : 'In-House';
                $nextReviewName = $submitUrrcRevision ? 'In-House Review' : 'UREC/IACUC Review';
                try {
                    notifyUsersByRole(
                        $pdo,
                        ['rps', 'admin'],
                        'Revised ' . $reviewStageName . ' manuscript submitted',
                        fullName($user) . ' submitted a revised ' . $reviewStageName . ' manuscript for '
                            . ($research['research_title'] ?? ('research #' . $researchId))
                            . '. RPS may now verify it and proceed to ' . $nextReviewName . '.',
                        'research',
                        $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $notificationError) {
                    error_log('Research revision notification error: ' . $notificationError->getMessage());
                }
            }

            setFlash(
                'success',
                $submitUrrcRevision
                    ? 'URRC revision saved. Click Submit Revision on the Research Details page to send it to RPS.'
                    : ($submitInhouseRevision
                        ? 'In-House revision saved. Click Submit Revision on the Research Details page to send it to RPS.'
                        : ($currentRecord['status'] === 'For Revision'
                            ? 'Research proposal revision saved successfully.'
                            : 'Research proposal updated successfully.'))
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

            // Keep database details off the page, but log the real cause.
            // In XAMPP inspect C:\xampp\apache\logs\error.log.
            $saveErrorRef = 'REV-' . strtoupper(bin2hex(random_bytes(3)));
            error_log('[ASU-REMS] research/edit.php ' . $saveErrorRef
                . ' research_id=' . $researchId
                . ' workflow=' . (string)($currentRecord['workflow_status'] ?? $research['workflow_status'] ?? '')
                . ' error=' . $e->getMessage());

            if ($e instanceof RuntimeException
                && str_starts_with($e->getMessage(), 'No current URRC Accepted with Revision decision')) {
                $errors[] = 'The current URRC revision round could not be verified. Please ask RPS to review this record. (' . $saveErrorRef . ')';
            } elseif ($e instanceof RuntimeException
                && str_starts_with($e->getMessage(), 'No current In-House Passed with Revision decision')) {
                $errors[] = 'The current In-House revision round could not be verified. Please ask RPS to review this record. (' . $saveErrorRef . ')';
            } elseif ($e instanceof RuntimeException
                && str_contains($e->getMessage(), 'already submitted')) {
                $errors[] = 'This revision has already been submitted to RPS. Editing is now locked.';
            } elseif ($e instanceof RuntimeException
                && str_contains($e->getMessage(), 'deadline')) {
                $errors[] = 'Revision cannot be saved because its review deadline is missing or expired. (' . $saveErrorRef . ')';
            } else {
                $errors[] = 'Unable to save the research revision. Error reference: ' . $saveErrorRef
                    . '. Check the XAMPP Apache error log for the exact cause.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN NAME FOR REVISION REMARK
|--------------------------------------------------------------------------
*/

$reviewerName = '';


if ($revisionRemark) {

    $reviewerName =
        trim(
            $revisionRemark[
                'first_name'
            ]
            .
            ' '
            .
            (
                !empty(
                    $revisionRemark[
                        'middle_name'
                    ]
                )
                    ? $revisionRemark[
                        'middle_name'
                    ]
                    . ' '
                    : ''
            )
            .
            $revisionRemark[
                'last_name'
            ]
        );
}


/*
|--------------------------------------------------------------------------
| USER INITIALS
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
                $user[
                    'first_name'
                ],
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
                $user[
                    'last_name'
                ],
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
.research-form-card #research_type {
    -webkit-appearance: none !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23007632' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 16px center !important;
    background-size: 16px 16px !important;
    padding-right: 44px !important;
}


/* Edit-specific feedback only; form layout is inherited directly from create.php. */
.edit-revision-feedback { display:flex; gap:12px; align-items:flex-start; padding:15px 18px;
    margin:0 0 17px; border:1px solid #ecdbaa; border-left:4px solid #c59725;
    border-radius:12px; background:#fffaf0; color:#654e21; }
.edit-revision-feedback-icon { display:grid; place-items:center; width:36px; height:36px;
    flex:0 0 36px; color:#9b7013; background:#fff0bb; border-radius:9px; }
.edit-revision-feedback h3 { margin:0 0 5px; font-size:12px; font-weight:800; }
.edit-revision-feedback p { margin:0 0 6px; font-size:11px; line-height:1.6; white-space:pre-wrap; overflow-wrap:anywhere; }
.edit-revision-feedback small { color:#786a51; font-size:9px; }
#revisedLinkViewMessage.is-link-opened { color:#007a33 !important; font-weight:700 !important; }

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
                        <?= $research['status'] === 'For Revision' ? 'Revise Research Proposal' : 'Edit Research Proposal'; ?>
                    </h1>

                    <p>
                        Update your existing research manuscript information and save your changes.
                    </p>
                </div>

                <a
                    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>"
                    class="btn btn-outline-asu"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Research Details
                </a>

            </div>

            <!-- =================================================
                 ERRORS
            ================================================== -->

            <?php if ($research['status'] === 'For Revision' && $revisionRemark): ?>
            <aside class="edit-revision-feedback" aria-label="Revision feedback">
                <span class="edit-revision-feedback-icon"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span>
                <div>
                    <h3>Revision Required</h3>
                    <p><?= e($revisionRemark['remarks']); ?></p>
                    <small>Reviewed by <?= e($reviewerName); ?> · <?= e(date('M d, Y h:i A', strtotime($revisionRemark['evaluated_at']))); ?></small>
                </div>
            </aside>
            <?php endif; ?>

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

                        <p>
                            <?= $research['status'] === 'For Revision' ? 'Update the proposal based on the revision feedback.' : 'Update your research proposal information.'; ?>
                        </p>
                    </div>

                </div>

                <div class="research-form-body">

                    <form
                        method="POST"
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

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">
                                        Lead Researcher
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        value="<?= e(fullName($user)); ?>"
                                        disabled
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">
                                        Institutional Assignment
                                    </label>

                                    <div class="research-assignment-box">

                                        <div class="research-assignment-item">
                                            <small>College</small>

                                            <strong>
                                                <?= !empty($research['college_name'])
                                                    ? e($research['college_name'])
                                                    : 'Not Assigned'; ?>
                                            </strong>
                                        </div>

                                        <div class="research-assignment-item">
                                            <small>Department</small>

                                            <strong>
                                                <?= !empty($research['department_name'])
                                                    ? e($research['department_name'])
                                                    : 'Not Assigned'; ?>
                                            </strong>
                                        </div>

                                    </div>
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
                                        <option value="Social Science" <?= strcasecmp((string)$researchCategory, 'Social Science') === 0 ? 'selected' : ''; ?>>Social Science</option>
                                        <option value="Technical" <?= strcasecmp((string)$researchCategory, 'Technical') === 0 ? 'selected' : ''; ?>>Technical</option>
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
                                        Funding Source
                                    </label>
                                    <input
                                        type="text"
                                        id="funding_source"
                                        name="funding_source"
                                        class="form-control"
                                        maxlength="150"
                                        placeholder="Example: Self-funded, ASU, CHED, DOST"
                                        value="<?= e($fundingSource); ?>"
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
                                <?= $research['status'] === 'For Revision' ? 'Revised Proposal Document' : 'Proposal Document'; ?>
                            </div>

                            <div class="research-upload-grid upload-layout-reordered">

                                <div class="research-upload-box manuscript-upload-box upload-link-box">
                                    <div class="upload-link-info">
                                        <div class="research-upload-icon">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </div>
                                        <div class="manuscript-upload-text">
                                            <h5><?= $research['status'] === 'For Revision' ? 'Revised Proposal Link' : 'Manuscript Link'; ?>
                                             <?php if ($research['status'] === 'For Revision'): ?><span class="required-mark">*</span><?php endif; ?></h5>
                                            
                                        </div>
                                    </div>
                                    <div class="upload-link-body">
                                        <div class="upload-link-controls">
                                            <input
                                                type="url"
                                                id="revised_document_link"
                                                name="revised_document_link"
                                                class="form-control"
                                                placeholder="https://drive.google.com/..."
                                                maxlength="255"
                                                value="<?= e($revisedDocumentLink); ?>"
                                                aria-label="Research document link"
                                                 <?= $research['status'] === 'For Revision' ? 'required' : ''; ?>
                                            >
                                            <a
                                                id="viewRevisedDocumentLink"
                                                href="#"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-outline-asu upload-link-view is-disabled upload-link-icon-btn"
                                                aria-disabled="true"
                                                aria-label="View research document link"
                                                title="View research document link"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                        <small class="research-field-note rems-upload-info-note" id="revisedLinkViewMessage" role="status" aria-live="polite">
                                            <span><?= $research['status'] === 'For Revision'
                                                 ? 'You must enter a shareable HTTPS document link and click the eye button to view it before saving.'
                                                 : 'You must click the eye button to view any HTTPS document link you provide before saving.'; ?></span>
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
                                    <?= $isUrrcRevisionEdit
                                        ? 'Save the revised URRC document link first. Then select Submit Revision on the Research Details page.'
                                        : ($isInhouseRevisionEdit
                                            ? 'Save the new In-House revision link first. Then select Submit Revision on the Research Details page.'
                                            : 'Save your updates here. You can continue the research workflow from the details page.'); ?>
                                </span>
                            </div>

                            <div class="research-action-buttons">

                                <a
                                    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>"
                                    class="btn btn-outline-asu"
                                >
                                    Cancel
                                </a>

                                <button
                                    type="submit"
                                    id="saveRevisedProposalButton"
                                    class="btn btn-asu"
                                    disabled
                                >
                                    <i class="bi bi-save me-1"></i>
                                    <?= $research['status'] === 'For Revision' ? 'Save Revision' : 'Save Changes'; ?>
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
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('revised_document_link');
    const eye = document.getElementById('viewRevisedDocumentLink');
    const save = document.getElementById('saveRevisedProposalButton');
    const note = document.getElementById('revisedLinkViewMessage');
    const noteText = note ? note.querySelector('span') : null;
    const form = input ? input.closest('form') : null;
    const csrf = form ? form.querySelector('input[name="csrf_token"]') : null;
    const linkRequired = <?= $research['status'] === 'For Revision' ? 'true' : 'false'; ?>;
    if (!input || !eye || !save || !note || !noteText || !form || !csrf) return;

    let validUrl = '';
    let openedUrl = '';
    let checking = false;
    let errorMessage = '';
    const instruction = linkRequired
        ? 'You must enter a shareable HTTPS document link and click the eye button to view it before saving.'
        : 'You must click the eye button to view any HTTPS document link you provide before saving.';

    function parseHttpsLink(raw) {
        const value = raw.trim();
        if (!value || value.length > 255) return '';
        try {
            const url = new URL(value);
            return (url.protocol === 'https:' && url.hostname && !url.username && !url.password)
                ? value
                : '';
        } catch (error) {
            return '';
        }
    }

    function updateControls() {
        const opened = !!validUrl && openedUrl === validUrl;
        const noLink = !input.value.trim();
        // Drafts may be edited without replacing a document. Revisions require a link.
        save.disabled = checking || (!noLink && (!validUrl || !opened)) || (linkRequired && noLink);
        note.classList.toggle('is-link-opened', opened);
        noteText.textContent = errorMessage || (opened
            ? 'Link opened successfully. You may now save your changes.'
            : instruction);
    }

    function refreshLink() {
        validUrl = parseHttpsLink(input.value);
        openedUrl = '';
        errorMessage = '';
        eye.removeAttribute('href');
        eye.classList.add('is-disabled');
        eye.setAttribute('aria-disabled', 'true');
        if (validUrl) {
            eye.href = validUrl;
            eye.classList.remove('is-disabled');
            eye.setAttribute('aria-disabled', 'false');
        }
        updateControls();
    }

    input.addEventListener('input', refreshLink);
    eye.addEventListener('click', async function (event) {
        if (!validUrl || checking) {
            event.preventDefault();
            return;
        }
        // The link opens in a new tab; this AJAX request only records the click
        // for the exact URL. It does not claim the document was fully read.
        const clickedUrl = validUrl;
        checking = true;
        errorMessage = '';
        updateControls();
        try {
            const params = new URLSearchParams({
                action: 'confirm_revised_document_link_view',
                csrf_token: csrf.value,
                revised_document_link: clickedUrl
            });
            const response = await fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString(),
                cache: 'no-store'
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error('Unable to verify your link view. Please click the eye button again.');
            if (validUrl === clickedUrl) openedUrl = clickedUrl;
        } catch (error) {
            errorMessage = 'Unable to verify your link view. Please click the eye button again.';
        } finally {
            checking = false;
            updateControls();
        }
    });
    form.addEventListener('submit', function (event) {
        const requiredAndEmpty = linkRequired && !input.value.trim();
        const hasUnviewedLink = !!input.value.trim() && (!validUrl || openedUrl !== validUrl);
        if (checking || requiredAndEmpty || hasUnviewedLink) event.preventDefault();
    });
    refreshLink();
});
</script>

<?php
require __DIR__ . '/../includes/footer.php';
?>
