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


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$user = currentUser();

$pageTitle = 'Research Manuscript Details';
$activePage = 'research';

$errors = [];


/*
|--------------------------------------------------------------------------
| UREC / IACUC CERTIFICATE UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$ethicsCertificateUploadDirectory =
    __DIR__ . '/../uploads/research/ethics_certificates';

$ethicsCertificateMaxFileSize =
    10 * 1024 * 1024; // 10 MB

$ethicsCertificateAllowedExtensions = [
    'pdf',
    'jpg',
    'jpeg',
    'png'
];

$ethicsCertificateAllowedMimeTypes = [
    'pdf' => [
        'application/pdf'
    ],

    'jpg' => [
        'image/jpeg',
        'image/pjpeg'
    ],

    'jpeg' => [
        'image/jpeg',
        'image/pjpeg'
    ],

    'png' => [
        'image/png',
        'image/x-png'
    ]
];


/*
|--------------------------------------------------------------------------
| SELF-INITIATED UPE CERTIFICATE UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$upeCertificateUploadDirectory =
    __DIR__ . '/../uploads/research/upe_certificates';

$upeCertificateMaxFileSize =
    10 * 1024 * 1024; // 10 MB

$upeCertificateAllowedExtensions = [
    'pdf',
    'jpg',
    'jpeg',
    'png'
];

$upeCertificateAllowedMimeTypes = [
    'pdf' => [
        'application/pdf'
    ],

    'jpg' => [
        'image/jpeg',
        'image/pjpeg'
    ],

    'jpeg' => [
        'image/jpeg',
        'image/pjpeg'
    ],

    'png' => [
        'image/png',
        'image/x-png'
    ]
];


/*
|--------------------------------------------------------------------------
| SELF-INITIATED COMPLETED RESEARCH / WHOLE MANUSCRIPT UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$selfInitiatedResearchUploadDirectory =
    __DIR__ . '/../uploads/research/self_initiated_completed_research';

$selfInitiatedResearchMaxFileSize =
    20 * 1024 * 1024; // 20 MB

$selfInitiatedResearchAllowedExtensions = [
    'pdf',
    'doc',
    'docx'
];

$selfInitiatedResearchAllowedMimeTypes = [
    'pdf' => [
        'application/pdf'
    ],

    'doc' => [
        'application/msword',
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
| SELF-INITIATED TERMINAL REPORT UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$selfInitiatedTerminalReportUploadDirectory =
    __DIR__ . '/../uploads/research/self_initiated_terminal_reports';

$selfInitiatedTerminalReportMaxFileSize =
    20 * 1024 * 1024; // 20 MB

$selfInitiatedTerminalReportAllowedExtensions = [
    'pdf',
    'doc',
    'docx'
];

$selfInitiatedTerminalReportAllowedMimeTypes = [
    'pdf' => [
        'application/pdf'
    ],

    'doc' => [
        'application/msword',
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
| SELF-INITIATED PROOF OF PUBLICATION / PRESENTATION UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$selfInitiatedProofUploadDirectory =
    __DIR__ . '/../uploads/research/self_initiated_publication_proofs';

$selfInitiatedProofMaxFileSize =
    20 * 1024 * 1024; // 20 MB

$selfInitiatedProofAllowedExtensions = [
    'pdf',
    'jpg',
    'jpeg',
    'png',
    'doc',
    'docx'
];

$selfInitiatedProofAllowedMimeTypes = [
    'pdf' => [
        'application/pdf'
    ],

    'jpg' => [
        'image/jpeg',
        'image/pjpeg'
    ],

    'jpeg' => [
        'image/jpeg',
        'image/pjpeg'
    ],

    'png' => [
        'image/png',
        'image/x-png'
    ],

    'doc' => [
        'application/msword',
        'application/octet-stream'
    ],

    'docx' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/octet-stream'
    ]
];






/* ASU-REMS: Shared link-only document helpers. Does not change workflow statuses. */
function researchExternalDocumentUrl(string $value): ?string
{
    $url = trim($value);
    if ($url === '' || strlen($url) > 255 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    $host = strtolower((string)$parts['host']);
    if ($host === 'localhost' || str_ends_with($host, '.local')
        || str_ends_with($host, '.localhost') || filter_var($host, FILTER_VALIDATE_IP)) {
        return null;
    }
    return $url;
}

/**
 * Create a safe, direct browser preview for supported external document links.
 * A Drive *folder* URL cannot preview a particular file; the user must share
 * a direct file URL. Other providers can restrict embedding.
 * No remote content is downloaded or fetched by the ASU-REMS server.
 */

/**
 * One and only resolver for saved and finally-submitted research revisions.
 *
 * Used by BOTH Faculty Submit Revision and the RPS View/Proceed controls.
 * The workflow status remains the authority for whether final submission
 * occurred; saving a draft alone never exposes RPS approval actions.
 *
 * A stage-tagged name made by edit.php is tied to its review history ID (or
 * evaluation ID). Older generic names are accepted only when the Faculty's
 * stage-specific Save Revision audit and the document's upload time agree.
 * This avoids using an old URRC link as a new In-House submission, or reusing
 * any prior round's revision after RPS asks for another revision.
 */
function remsChooseResearchRevisionDocument(
    array $documents,
    int $researchId,
    int $facultyId,
    string $stage,
    int $decisionHistoryId,
    int $evaluationId,
    ?int $decisionTime,
    ?int $saveTime,
    ?int $submitTime,
    bool $hasCurrentSave
): ?array {
    $stageCode = $stage === 'URRC' ? 'rvu' : 'rvi';
    $otherCode = $stage === 'URRC' ? 'rvi' : 'rvu';
    $stageLong = $stage === 'URRC' ? 'linked_urrc_revision_' : 'linked_inhouse_revision_';
    $otherLong = $stage === 'URRC' ? 'linked_inhouse_revision_' : 'linked_urrc_revision_';
    $best = null;
    $bestScore = -1;

    foreach ($documents as $doc) {
        if (strcasecmp(trim((string)($doc['document_type'] ?? '')), 'Revised Proposal') !== 0
            || (int)($doc['uploaded_by'] ?? 0) !== $facultyId
            || (int)($doc['document_id'] ?? 0) < 1
            || researchExternalDocumentUrl((string)($doc['file_path'] ?? '')) === null) {
            continue;
        }
        $stored = strtolower(trim((string)($doc['stored_name'] ?? '')));
        if (str_starts_with($stored, $otherCode) || str_starts_with($stored, $otherLong)) {
            continue;
        }
        $uploadTime = strtotime((string)($doc['uploaded_at'] ?? ''));
        $score = -1;

        // Best match: today's edit.php includes the specific RPS decision's
        // immutable history_id in the stored name, not a fragile timestamp.
        if (preg_match('/^' . $stageCode . 'h([0-9]+)_r' . $researchId . '_/i', $stored, $m)) {
            if ($decisionHistoryId > 0 && (int)$m[1] === $decisionHistoryId) {
                $score = 100;
            }
        } elseif (preg_match('/^' . $stageCode . '(?:e)?([0-9]+)_r' . $researchId . '_/i', $stored, $m)) {
            if ($evaluationId > 0 && (int)$m[1] === $evaluationId) {
                $score = 90;
            }
        } elseif (preg_match('/^' . $stageCode . 't[a-f0-9]{12}_r' . $researchId . '_/i', $stored)) {
            if ($hasCurrentSave && $uploadTime !== false && $saveTime !== null
                && abs($uploadTime - $saveTime) <= 300) {
                $score = 70;
            }
        } elseif (str_starts_with($stored, $stageLong)) {
            $legacyEvaluation = 0;
            if (preg_match('/^' . preg_quote($stageLong, '/') . 'e([0-9]+)_(?:r)?' . $researchId . '_/i', $stored, $m)) {
                $legacyEvaluation = (int)$m[1];
            }
            if ($legacyEvaluation > 0 && $evaluationId > 0 && $legacyEvaluation === $evaluationId) {
                $score = 90;
            } elseif ($hasCurrentSave && $uploadTime !== false && $saveTime !== null
                && abs($uploadTime - $saveTime) <= 300) {
                $score = 60;
            }
        } elseif ($hasCurrentSave && str_starts_with($stored, 'linked_revision_' . $researchId . '_')
            && $uploadTime !== false && $saveTime !== null
            && abs($uploadTime - $saveTime) <= 300) {
            // A previous URRC file cannot qualify without this stage's
            // current Save Revision history. The save and insert are atomic.
            $score = 50;
        }

        if ($score < 0) {
            continue;
        }
        // For legacy, time-based matches reject a document from before the
        // review decision. For h/e ID-based matches the round key is stronger
        // than timestamp comparisons that may differ by server timezone.
        if ($score < 90 && $uploadTime !== false
            && $decisionTime !== null && $uploadTime < $decisionTime - 5) {
            continue;
        }
        if ($score < 90 && $uploadTime !== false
            && $submitTime !== null && $uploadTime > $submitTime + 300) {
            continue;
        }
        if ($best === null || $score > $bestScore
            || ($score === $bestScore
                && (int)$doc['document_id'] > (int)$best['document_id'])) {
            $best = $doc;
            $bestScore = $score;
        }
    }
    return $best;
}

function remsResearchRevisionDocumentForStage(
    PDO $pdo,
    int $researchId,
    array $research,
    array $documents,
    string $stage
): ?array {
    $configs = [
        'URRC' => [
            'required' => 'URRC_REVISION_REQUIRED',
            'submitted' => 'URRC_REVISED_SUBMITTED',
            'decision' => 'URRC result recorded: Accepted with Revision%',
            'evaluation' => 'URRC Accepted with Revision',
            'save' => 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.',
            'final' => 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.',
        ],
        'INHOUSE' => [
            'required' => 'INHOUSE_REVISION_REQUIRED',
            'submitted' => 'INHOUSE_REVISED_SUBMITTED',
            'decision' => 'In-House result recorded: Passed with Revision%',
            'evaluation' => 'In-House Passed with Revision',
            'save' => 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.',
            'final' => 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.',
        ],
    ];
    if (!isset($configs[$stage])) {
        return null;
    }
    $cfg = $configs[$stage];
    $status = (string)($research['workflow_status'] ?? '');
    $pending = $status === $cfg['required'];
    $submitted = $status === $cfg['submitted'];
    if ((!$pending && !$submitted) || (int)($research['is_archived'] ?? 0) !== 0) {
        return null;
    }
    $facultyId = (int)($research['researcher_id'] ?? 0);
    if ($facultyId <= 0) {
        return null;
    }

    $decisionStmt = $pdo->prepare("SELECT history_id, changed_at
        FROM research_status_history WHERE research_id = ?
        AND new_status = 'For Revision' AND remarks LIKE ?
        ORDER BY history_id DESC LIMIT 1");
    $decisionStmt->execute([$researchId, $cfg['decision']]);
    $decision = $decisionStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $evalStmt = $pdo->prepare("SELECT evaluation_id, evaluated_at
        FROM research_evaluations WHERE research_id = ? AND decision = ?
        ORDER BY evaluation_id DESC LIMIT 1");
    $evalStmt->execute([$researchId, $cfg['evaluation']]);
    $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $saveStmt = $pdo->prepare("SELECT history_id, changed_at FROM research_status_history
        WHERE research_id = ? AND changed_by = ? AND remarks = ?
        ORDER BY history_id DESC LIMIT 1");
    $saveStmt->execute([$researchId, $facultyId, $cfg['save']]);
    $save = $saveStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // Old records have the exact sentence; newly submitted revisions append
    // [REVISION_DOC_ID=N;ROUND=hN/eN/t...] to pin the specific document forever.
    $submitStmt = $pdo->prepare("SELECT history_id, changed_at, remarks FROM research_status_history
        WHERE research_id = ? AND changed_by = ?
          AND old_status = 'For Revision' AND new_status = 'Submitted'
          AND remarks LIKE ? ORDER BY history_id DESC LIMIT 1");
    $submitStmt->execute([$researchId, $facultyId, $cfg['final'] . '%']);
    $final = $submitStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $decisionId = (int)($decision['history_id'] ?? 0);
    $saveId = (int)($save['history_id'] ?? 0);
    $finalId = (int)($final['history_id'] ?? 0);
    if ($decisionId > 0 && $saveId > 0 && $saveId <= $decisionId) {
        $save = null;
    }
    if ($decisionId > 0 && $finalId > 0 && $finalId <= $decisionId) {
        $final = null;
    }

    // After final submission, the submission audit record is the definitive
    // document pointer. This is more reliable than checking filename patterns,
    // which previously caused the RPS View / Proceed controls to disappear.
    // For legacy submissions without the marker, retain the older resolver.
    if ($submitted && $final
        && preg_match('/\[REVISION_DOC_ID=([0-9]+);ROUND=(h[0-9]+|e[0-9]+|t[a-f0-9]{12})\]/i',
            (string)$final['remarks'], $submittedMatch)) {
        $submittedDocumentId = (int)$submittedMatch[1];
        $submittedRoundKey = strtolower($submittedMatch[2]);
        $expectedHistoryKey = $decisionId > 0 ? 'h' . $decisionId : null;
        // A later revision decision must never reuse an earlier submitted link.
        if ($expectedHistoryKey !== null && $submittedRoundKey !== $expectedHistoryKey) {
            return null;
        }
        foreach ($documents as $submittedDocument) {
            if ((int)($submittedDocument['document_id'] ?? 0) === $submittedDocumentId
                && (int)($submittedDocument['uploaded_by'] ?? 0) === $facultyId
                && strcasecmp(trim((string)($submittedDocument['document_type'] ?? '')), 'Revised Proposal') === 0
                && researchExternalDocumentUrl((string)($submittedDocument['file_path'] ?? '')) !== null) {
                return $submittedDocument;
            }
        }
        // Don't display an unrelated previous manuscript if a committed ID
        // is missing: the RPS verification button must remain locked.
        return null;
    }

    // The pending Faculty button requires a *new* saved revision in this round;
    // an older file alone never enables it. Submit Revision sets submitted status.
    if ($pending && $save === null) {
        return null;
    }

    $decisionTime = $decision ? strtotime((string)$decision['changed_at']) : false;
    $evaluationTime = $evaluation ? strtotime((string)$evaluation['evaluated_at']) : false;
    if ($decisionTime === false) {
        $decisionTime = $evaluationTime;
    }
    $saveTime = $save ? strtotime((string)$save['changed_at']) : false;
    $submitTime = $final ? strtotime((string)$final['changed_at']) : false;
    if ($submitTime === false && $submitted) {
        $submitTime = strtotime((string)($research['workflow_updated_at'] ?? ''));
    }
    $evalId = (int)($evaluation['evaluation_id'] ?? 0);
    if ($decision && $evaluation && $decisionTime !== false && $evaluationTime !== false
        && abs($evaluationTime - $decisionTime) > 20) {
        // The evaluation may belong to an older round, don't match it by ID.
        $evalId = 0;
    }

    return remsChooseResearchRevisionDocument(
        $documents, $researchId, $facultyId, $stage,
        $decisionId, $evalId,
        $decisionTime === false ? null : $decisionTime,
        $saveTime === false ? null : $saveTime,
        $submitTime === false ? null : $submitTime,
        $save !== null
    );
}

// Retained name for compatibility with earlier RPS code; both pathways now
// call the exact same resolver as the Faculty Submit Revision button.
function remsRpsSubmittedRevisionLinkDocument(
    PDO $pdo,
    int $researchId,
    array $research,
    array $documents,
    string $stage
): ?array {
    return remsResearchRevisionDocumentForStage($pdo, $researchId, $research, $documents, $stage);
}

function researchExternalDocumentPreviewUrl(string $documentUrl): ?string
{
    $url = researchExternalDocumentUrl($documentUrl);
    if ($url === null) {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');

    // A Google Drive FILE is embeddable using its /preview endpoint.
    if ($host === 'drive.google.com') {
        $fileId = null;
        if (preg_match('~^/file/d/([A-Za-z0-9_-]{10,})/(?:view|preview|edit)?/?$~', $path, $matches)) {
            $fileId = $matches[1];
        } elseif (in_array(rtrim($path, '/'), ['/open', '/uc', '/thumbnail'], true)) {
            $params = [];
            parse_str((string)($parts['query'] ?? ''), $params);
            if (isset($params['id']) && is_string($params['id'])
                && preg_match('/^[A-Za-z0-9_-]{10,}$/', $params['id'])) {
                $fileId = $params['id'];
            }
        }
        return $fileId === null
            ? null
            : 'https://drive.google.com/file/d/' . rawurlencode($fileId) . '/preview';
    }

    // Docs, Sheets and Slides have their own inline viewers.
    if ($host === 'docs.google.com'
        && preg_match('~^/(document|spreadsheets|presentation)/d/([A-Za-z0-9_-]{10,})(?:/|$)~', $path, $matches)) {
        $type = $matches[1];
        $fileId = rawurlencode($matches[2]);
        if ($type === 'presentation') {
            return 'https://docs.google.com/presentation/d/' . $fileId
                . '/embed?start=false&loop=false&delayms=3000';
        }
        return 'https://docs.google.com/' . $type . '/d/' . $fileId . '/preview';
    }

    // Public direct PDF links may be displayed by the browser's PDF viewer.
    if (preg_match('/\.pdf$/i', $path)) {
        return $url;
    }

    return null;
}

function researchValidateStepLink(string $oldFileField, string $label, array &$errors): ?array
{
    $linkField = preg_replace('/_file$/', '_link', $oldFileField);
    $url = researchExternalDocumentUrl((string)($_POST[$linkField] ?? ''));
    if ($url === null) {
        $errors[] = 'Please enter a valid HTTPS link for ' . $label . ' (up to 255 characters).';
        return null;
    }
    return [
        'url' => $url,
        'original_name' => $label . ' Link',
        'extension' => 'url',
        'size' => 0
    ];
}

function researchStoreStepLink(array $link, string $prefix, int $researchId): array
{
    return [
        'stored_name' => 'linked_' . $prefix . '_' . $researchId . '_'
            . bin2hex(random_bytes(5)),
        'physical_path' => null,
        'database_path' => $link['url']
    ];
}

/*
|--------------------------------------------------------------------------
| TERMINAL REPORT — PERSISTENT RPS OK CHECK
|--------------------------------------------------------------------------
| A permanent status-history audit marker gates permit generation. The
| existing TERMINAL_REPORT_SUBMITTED status and research queues remain intact.
*/
function researchTerminalReportConfirmed(PDO $pdo, int $researchId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM research_status_history rsh
        INNER JOIN users approver ON approver.user_id = rsh.changed_by
        WHERE rsh.research_id = ?
          AND approver.role = 'rps'
          AND rsh.remarks = 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]'
        LIMIT 1
    ");
    $stmt->execute([$researchId]);
    return (bool)$stmt->fetchColumn();
}

/*
|--------------------------------------------------------------------------
| DISPLAY ROLE
|--------------------------------------------------------------------------
*/

$displayRole =
    ($user['role'] ?? '') === 'rps'
        ? 'Research Processing Staff (RPS)'
        : roleLabel(
            $user['role'] ?? ''
        );


/*
|--------------------------------------------------------------------------
| WORKFLOW HELPERS
|--------------------------------------------------------------------------
*/

function ensureResearchManuscriptNumber(
    PDO $pdo,
    int $researchId
): string {

    /*
    |--------------------------------------------------------------------------
    | LOCK RESEARCH RECORD
    |--------------------------------------------------------------------------
    */

    $researchStmt =
        $pdo->prepare("
            SELECT
                manuscript_no

            FROM research_proposals

            WHERE research_id = ?

            FOR UPDATE
        ");

    $researchStmt->execute([
        $researchId
    ]);

    $researchRow =
        $researchStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$researchRow) {

        throw new RuntimeException(
            'Research record not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EXISTING MANUSCRIPT NUMBER
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $researchRow[
                'manuscript_no'
            ]
        )
    ) {

        return
            (string)$researchRow[
                'manuscript_no'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT YEAR SEQUENCE
    |--------------------------------------------------------------------------
    */

    $sequenceYear =
        (int)date('Y');


    $insertSequence =
        $pdo->prepare("
            INSERT INTO manuscript_sequences
            (
                sequence_year,
                last_number
            )
            VALUES
            (
                ?,
                0
            )

            ON DUPLICATE KEY UPDATE
                sequence_year = VALUES(sequence_year)
        ");

    $insertSequence->execute([
        $sequenceYear
    ]);


    $sequenceStmt =
        $pdo->prepare("
            SELECT
                last_number

            FROM manuscript_sequences

            WHERE sequence_year = ?

            FOR UPDATE
        ");

    $sequenceStmt->execute([
        $sequenceYear
    ]);

    $lastNumber =
        (int)$sequenceStmt
            ->fetchColumn();


    $nextNumber =
        $lastNumber + 1;


    $updateSequence =
        $pdo->prepare("
            UPDATE manuscript_sequences

            SET last_number = ?

            WHERE sequence_year = ?
        ");

    $updateSequence->execute([
        $nextNumber,
        $sequenceYear
    ]);


    $manuscriptNo =
        sprintf(
            'ASU-RES-%04d-%04d',
            $sequenceYear,
            $nextNumber
        );


    $updateResearch =
        $pdo->prepare("
            UPDATE research_proposals

            SET manuscript_no = ?

            WHERE research_id = ?
              AND manuscript_no IS NULL
        ");

    $updateResearch->execute([
        $manuscriptNo,
        $researchId
    ]);


    return
        $manuscriptNo;
}


function isDetailedResearchWorkflow(
    array $research
): bool {

    $workflowStatus =
        trim(
            (string)(
                $research[
                    'workflow_status'
                ]
                ?? ''
            )
        );


    if (
        $workflowStatus === ''
    ) {

        return false;
    }


    return
        !str_starts_with(
            $workflowStatus,
            'LEGACY_'
        );
}


/**
 * A newly selected path is marked by Step 2's system-generated history row.
 * Legacy research has no such marker, so older stage transitions stay intact.
 * No additional database column or schema change is needed.
 */
function researchSelectedEarlyPath(PDO $pdo, int $researchId, string $track): bool
{
    $pathName = match ($track) {
        'REGULAR' => 'For URRC',
        'SELF_INITIATED' => 'Self-Initiated',
        default => null
    };
    if ($pathName === null) {
        return false;
    }

    // An In-House revision can also be switched to Self-Initiated by Faculty.
    // Use a separate, accurate audit marker rather than claiming the choice
    // was made immediately after submission.
    $alternate = $track === 'SELF_INITIATED'
        ? 'Faculty switched from In-House revision to Self-Initiated pathway.'
        : '';
    $stmt = $pdo->prepare("
        SELECT 1 FROM research_status_history
        WHERE research_id = ? AND (remarks = ? OR remarks = ? OR remarks = ?)
        LIMIT 1
    " );
    $stmt->execute([
        $researchId,
        'Faculty selected the ' . $pathName . ' pathway after proposal submission.',
        $alternate,
        $track === 'SELF_INITIATED'
            ? 'Faculty chose Self-Initiated after the 180-day In-House revision deadline. The prior automatic archive remains recorded in research_status_history.'
            : ''
    ]);
    return (bool)$stmt->fetchColumn();
}

/**
 * New For URRC submissions must be explicitly forwarded by RPS before URRC.
 * Earlier Step 2/3 test records already stored FOR_URRC directly; the
 * original faculty-selection history marker lets them enter RPS intake too.
 * Legacy pre-reorder URRC records have no marker and remain unchanged.
 */
function researchUrrcAwaitingRpsIntake(PDO $pdo, int $researchId, array $research): bool
{
    $status = (string)($research['workflow_status'] ?? '');
    if ($status === 'FOR_URRC_PATH_SELECTED') {
        return true;
    }
    if ($status !== 'FOR_URRC' || !researchSelectedEarlyPath($pdo, $researchId, 'REGULAR')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM research_status_history
        WHERE research_id = ? AND remarks = ? LIMIT 1');
    $stmt->execute([
        $researchId,
        'RPS forwarded selected For URRC research from New Submissions to URRC Review.'
    ]);
    return !$stmt->fetchColumn();
}

function researchEthicsUploadErrorMessage(
    int $error
): string {

    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded UREC/IACUC certificate exceeds the allowed file size.',

        UPLOAD_ERR_PARTIAL =>
            'The UREC/IACUC certificate was only partially uploaded. Please try again.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary upload folder is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The UREC/IACUC certificate could not be saved on the server.',

        UPLOAD_ERR_EXTENSION =>
            'The UREC/IACUC certificate upload was stopped by a PHP extension.',

        default =>
            'Unable to upload the UREC/IACUC certificate. Please try again.'
    };
}


function validateResearchEthicsCertificateUpload(
    array $allowedExtensions,
    array $allowedMimeTypes,
    int $maxFileSize,
    array &$errors
): ?array {

    if (
        !isset($_FILES['ethics_certificate_file'])
        ||
        (
            $_FILES['ethics_certificate_file']['error']
            ?? UPLOAD_ERR_NO_FILE
        ) === UPLOAD_ERR_NO_FILE
    ) {

        $errors[] =
            'Please upload the UREC/IACUC certificate or official result file.';

        return null;
    }


    $file =
        $_FILES['ethics_certificate_file'];


    if (
        ($file['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_OK
    ) {

        $errors[] =
            researchEthicsUploadErrorMessage(
                (int)$file['error']
            );

        return null;
    }


    $fileSize =
        (int)($file['size'] ?? 0);


    if ($fileSize <= 0) {

        $errors[] =
            'The uploaded UREC/IACUC certificate is empty.';

        return null;
    }


    if ($fileSize > $maxFileSize) {

        $errors[] =
            'UREC/IACUC certificate must not exceed 10 MB.';

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
            'UREC/IACUC certificate must be PDF, JPG, JPEG, or PNG only.';

        return null;
    }


    if (
        empty($file['tmp_name'])
        ||
        !is_uploaded_file($file['tmp_name'])
    ) {

        $errors[] =
            'The uploaded UREC/IACUC certificate is invalid.';

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
            'The uploaded UREC/IACUC certificate does not match its file format.';

        return null;
    }


    return [
        'tmp_name' => $file['tmp_name'],
        'original_name' => $originalName,
        'extension' => $extension,
        'size' => $fileSize
    ];
}


function ensureResearchDocumentTypesAreFlexible(
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


function moveResearchEthicsCertificateUpload(
    array $certificateFile,
    string $uploadDirectory,
    int $researchId
): array {

    if (!is_dir($uploadDirectory)) {

        if (
            !mkdir(
                $uploadDirectory,
                0755,
                true
            )
            &&
            !is_dir($uploadDirectory)
        ) {

            throw new RuntimeException(
                'Unable to create UREC/IACUC certificate upload directory.'
            );
        }
    }


    $storedName =
        'urec_iacuc_certificate_' .
        $researchId .
        '_' .
        date('YmdHis') .
        '_' .
        bin2hex(random_bytes(8)) .
        '.' .
        $certificateFile['extension'];


    $physicalPath =
        $uploadDirectory .
        DIRECTORY_SEPARATOR .
        $storedName;


    if (
        !move_uploaded_file(
            $certificateFile['tmp_name'],
            $physicalPath
        )
    ) {

        throw new RuntimeException(
            'Unable to save the uploaded UREC/IACUC certificate.'
        );
    }


    return [
        'stored_name' => $storedName,
        'physical_path' => $physicalPath,
        'database_path' => 'uploads/research/ethics_certificates/' . $storedName
    ];
}




/*
|--------------------------------------------------------------------------
| SELF-INITIATED UPE CERTIFICATE HELPERS
|--------------------------------------------------------------------------
*/

function researchUpeUploadErrorMessage(
    int $error
): string {

    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded UPE certificate exceeds the allowed file size.',

        UPLOAD_ERR_PARTIAL =>
            'The UPE certificate was only partially uploaded. Please try again.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary upload folder is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The UPE certificate could not be saved on the server.',

        UPLOAD_ERR_EXTENSION =>
            'The UPE certificate upload was stopped by a PHP extension.',

        default =>
            'Unable to upload the UPE certificate. Please try again.'
    };
}


function validateResearchUpeCertificateUpload(string $fieldName, array $allowedExtensions, array $allowedMimeTypes, int $maxFileSize, array &$errors): ?array
{
    $slot = (int)($_POST['upe_slot'] ?? 0);
    return researchValidateStepLink($fieldName, 'UPE Certificate ' . $slot, $errors);
}


function moveResearchUpeCertificateUpload(array $certificateFile, string $uploadDirectory, int $researchId, int $slot): array
{
    return researchStoreStepLink($certificateFile, 'upe_certificate_' . $slot, $researchId);
}


function researchSelfInitiatedResearchUploadErrorMessage(
    int $error
): string {

    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The completed research file exceeds the allowed file size.',

        UPLOAD_ERR_PARTIAL =>
            'The completed research file was only partially uploaded. Please try again.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary upload folder is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The completed research file could not be saved on the server.',

        UPLOAD_ERR_EXTENSION =>
            'The completed research file upload was stopped by a PHP extension.',

        default =>
            'Unable to upload the completed research file. Please try again.'
    };
}


function validateResearchSelfInitiatedCompletedResearchUpload(string $fieldName, array $allowedExtensions, array $allowedMimeTypes, int $maxFileSize, array &$errors): ?array
{
    return researchValidateStepLink($fieldName, 'Completed Research / Whole Manuscript', $errors);
}


function moveResearchSelfInitiatedCompletedResearchUpload(array $researchFile, string $uploadDirectory, int $researchId): array
{
    return researchStoreStepLink($researchFile, 'completed_research', $researchId);
}


/*
|--------------------------------------------------------------------------
| SELF-INITIATED TERMINAL REPORT HELPERS
|--------------------------------------------------------------------------
*/

function researchSelfInitiatedTerminalReportUploadErrorMessage(
    int $error
): string {

    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The Terminal Report exceeds the allowed file size.',

        UPLOAD_ERR_PARTIAL =>
            'The Terminal Report was only partially uploaded. Please try again.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary upload folder is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The Terminal Report could not be saved on the server.',

        UPLOAD_ERR_EXTENSION =>
            'The Terminal Report upload was stopped by a PHP extension.',

        default =>
            'Unable to upload the Terminal Report. Please try again.'
    };
}


function validateResearchSelfInitiatedTerminalReportUpload(string $fieldName, array $allowedExtensions, array $allowedMimeTypes, int $maxFileSize, array &$errors): ?array
{
    return researchValidateStepLink($fieldName, 'Terminal Report', $errors);
}


function moveResearchSelfInitiatedTerminalReportUpload(array $terminalReportFile, string $uploadDirectory, int $researchId): array
{
    return researchStoreStepLink($terminalReportFile, 'terminal_report', $researchId);
}



/*
|--------------------------------------------------------------------------
| SELF-INITIATED PUBLICATION UPLOAD HELPERS
|--------------------------------------------------------------------------
*/

function researchSelfInitiatedProofUploadErrorMessage(
    int $error
): string {

    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The proof file exceeds the allowed file size.',

        UPLOAD_ERR_PARTIAL =>
            'The proof file was only partially uploaded. Please try again.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary upload folder is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The proof file could not be saved on the server.',

        UPLOAD_ERR_EXTENSION =>
            'The proof upload was stopped by a PHP extension.',

        default =>
            'Unable to upload the publication file. Please try again.'
    };
}


function validateResearchSelfInitiatedProofUpload(string $fieldName, array $allowedExtensions, array $allowedMimeTypes, int $maxFileSize, array &$errors): ?array
{
    return researchValidateStepLink($fieldName, 'Publication / Presentation Proof', $errors);
}


function moveResearchSelfInitiatedProofUpload(array $proofFile, string $uploadDirectory, int $researchId): array
{
    return researchStoreStepLink($proofFile, 'publication_proof', $researchId);
}





/*
|--------------------------------------------------------------------------
| PRIVATE RPS SECTION REMARKS
|--------------------------------------------------------------------------
|
| These remarks are comments only. Saving a remark does NOT change the
| research workflow status and does NOT automatically request a revision.
| Remarks are shown only to Research Processing Staff and the faculty
| researcher who owns the proposal.
|
*/

function ensureResearchSectionRemarksTable(
    PDO $pdo
): void {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS research_section_remarks (
            remark_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            research_id INT UNSIGNED NOT NULL,
            section_key VARCHAR(40) NOT NULL,
            remark_text TEXT NOT NULL,
            created_by INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (remark_id),
            UNIQUE KEY uq_research_section_remark (
                research_id,
                section_key
            ),
            KEY idx_section_remark_creator (created_by),

            CONSTRAINT fk_section_remark_research
                FOREIGN KEY (research_id)
                REFERENCES research_proposals (research_id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,

            CONSTRAINT fk_section_remark_creator
                FOREIGN KEY (created_by)
                REFERENCES users (user_id)
                ON UPDATE CASCADE
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
}



function researchSectionRemarksAreLocked(
    array $research
): bool {
    // Only a researcher-selected pathway opens the initial RPS comment window.
    // Prior to selection (AWAITING_PATHWAY_SELECTION), comments cannot be saved.
    // After initial intake, they remain visible to the researcher and RPS,
    // but they can no longer be edited through this initial review panel.
    if ((int)($research['is_archived'] ?? 0) !== 0) {
        return true;
    }

    $workflowStatus = strtoupper(trim((string)($research['workflow_status'] ?? '')));
    $pathway = strtoupper(trim((string)($research['submission_track'] ?? '')));

    return !(
        ($workflowStatus === 'FOR_URRC_PATH_SELECTED' && $pathway === 'REGULAR')
        || ($workflowStatus === 'SELF_INITIATED_UPE_REQUIRED' && $pathway === 'SELF_INITIATED')
    );
}


function renderResearchSectionRemarkBox(
    array $user,
    array $research,
    array $sectionRemarks,
    string $sectionKey,
    string $sectionLabel
): void {

    $isRps =
        ($user['role'] ?? '') === 'rps';

    $isOwner =
        ($user['role'] ?? '') === 'faculty'
        &&
        (int)($research['researcher_id'] ?? 0)
        ===
        (int)($user['user_id'] ?? 0);

    if (!$isRps && !$isOwner) {
        return;
    }

    $remark =
        $sectionRemarks[$sectionKey]
        ?? null;

    // Faculty should only see the control when an RPS comment actually exists.
    if ($isOwner && !$remark) {
        return;
    }

    $remarkText =
        trim(
            (string)(
                $remark['remark_text']
                ?? ''
            )
        );

    $authorName =
        trim(
            (string)(
                $remark['author_name']
                ?? ''
            )
        );

    $remarkDate =
        (string)(
            $remark['updated_at']
            ?? $remark['created_at']
            ?? ''
        );

    $formattedDate = '';

    if ($remarkDate !== '') {
        $timestamp = strtotime($remarkDate);

        if ($timestamp !== false) {
            $formattedDate = date('M d, Y g:i A', $timestamp);
        }
    }

    $commentsLocked =
        researchSectionRemarksAreLocked($research);

    /*
     * RPS AFTER RESEARCHER PATHWAY SELECTION (initial review only)
     * - no comment: Add Comment
     * - saved comment: View 1 comment, opening the editable modal
     */
    if ($isRps && !$commentsLocked) {
        ?>

        <div class="section-comment-control">

            <button
                type="button"
                class="section-comment-button<?= $remark ? ' has-saved-comment' : ''; ?>"
                data-bs-toggle="modal"
                data-bs-target="#rpsSectionCommentModal"
                data-section-key="<?= e($sectionKey); ?>"
                data-section-label="<?= e($sectionLabel); ?>"
                data-existing-comment="<?= e($remarkText); ?>"
            >
                <i class="bi <?= $remark ? 'bi-chat-left-text' : 'bi-chat-left-text'; ?>"></i>
                <span class="section-comment-button-label">
                    <?= $remark ? 'View 1 comment' : 'Add Comment'; ?>
                </span>
            </button>

            <span
                class="section-comment-state"
                data-comment-state="<?= e($sectionKey); ?>"
            ></span>

        </div>

        <?php
        return;
    }

    // After locking there is nothing to show if no comment was ever saved.
    if (!$remark) {
        return;
    }

    /*
     * FACULTY OWNER OR RPS AFTER INITIAL REVIEW
     * Keep the page subtle: only a small "View 1 comment" link is shown.
     * The actual text is revealed in a read-only modal.
     */
    ?>

    <div class="section-comment-control section-comment-readonly-control">

        <button
            type="button"
            class="section-comment-button section-comment-view-button"
            data-bs-toggle="modal"
            data-bs-target="#sectionRemarkViewModal"
            data-section-label="<?= e($sectionLabel); ?>"
            data-comment-text="<?= e($remarkText); ?>"
            data-comment-author="<?= e($authorName); ?>"
            data-comment-date="<?= e($formattedDate); ?>"
        >
            <i class="bi bi-chat-left-text"></i>
            <span>View 1 comment</span>
        </button>

        <span
            class="section-comment-lock"
            title="Comment locked"
            aria-label="Comment locked"
        >
            <i class="bi bi-lock-fill" aria-hidden="true"></i>
        </span>

    </div>

    <?php
}

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

function loadResearchProposal(
    PDO $pdo,
    int $researchId
): array|false {

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
            rp.progress_percentage,

            rp.manuscript_no,
            rp.submission_track,
            rp.workflow_status,
            rp.workflow_stage,
            rp.is_archived,
            rp.archived_reason,
            rp.archived_at,
            rp.workflow_updated_at,

            rp.submitted_at,
            rp.approved_at,
            rp.completed_at,

            rp.created_at,
            rp.updated_at,

            u.first_name,
            u.middle_name,
            u.last_name,
            u.email,

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

    return $stmt->fetch(
        PDO::FETCH_ASSOC
    );
}


$research =
    loadResearchProposal(
        $pdo,
        $researchId
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
| RECORD ACCESS
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
        'You are not authorized to view this research proposal.'
    );

    redirect(
        BASE_URL . '/research/index.php'
    );
}


if (
    in_array(
        $user['role'],
        [
            'admin',
            'rps'
        ],
        true
    )
    &&
    $research['status'] === 'Draft'
) {

    setFlash(
        'danger',
        'Draft proposals are private to the faculty researcher.'
    );

    redirect(
        BASE_URL . '/research/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| ADMINISTRATOR VIEW-ONLY ENFORCEMENT
|--------------------------------------------------------------------------
|
| Administrators manage the system, not the research approval workflow.
| Normal record requests are sent to the dedicated read-only Admin view.
| Document and generated-certificate requests remain available for oversight.
|
*/
if (
    ($user['role'] ?? '') === 'admin'
    && !isset($_GET['document_id'])
    && !isset($_GET['certificate'])
) {
    redirect(
        BASE_URL . '/admin/records/research_view.php?id=' . $researchId
    );
}



/*
|--------------------------------------------------------------------------
| LOAD PRIVATE RPS SECTION REMARKS
|--------------------------------------------------------------------------
*/

// Automatically close overdue URRC revision windows before processing any action.
// Re-fetch the research record because the archive may have changed its status.
remsArchiveExpiredUrrcRevisions($pdo, $researchId);
remsArchiveExpiredInhouseRevisions($pdo, $researchId);
$research = loadResearchProposal($pdo, $researchId);
$urrcRevisionDeadline = remsUrrcRevisionDeadline($pdo, $researchId);
$inhouseRevisionDeadline = remsInhouseRevisionDeadline($pdo, $researchId);
$isEarlyUrrcRevision = remsIsNewUrrcPath($pdo, $researchId);

ensureResearchSectionRemarksTable(
    $pdo
);

$researchSectionRemarkLabels = [
    'abstract' => 'Abstract',
    'objectives' => 'Objectives',
    'methodology' => 'Methodology',
    'expected_output' => 'Expected Output'
];

$sectionRemarks = [];

$sectionRemarkStmt =
    $pdo->prepare("
        SELECT
            rsr.remark_id,
            rsr.section_key,
            rsr.remark_text,
            rsr.created_by,
            rsr.created_at,
            rsr.updated_at,

            TRIM(
                CONCAT_WS(
                    ' ',
                    u.first_name,
                    NULLIF(u.middle_name, ''),
                    u.last_name
                )
            ) AS author_name

        FROM research_section_remarks rsr

        LEFT JOIN users u
            ON u.user_id = rsr.created_by

        WHERE rsr.research_id = ?
    ");

$sectionRemarkStmt->execute([
    $researchId
]);

foreach (
    $sectionRemarkStmt->fetchAll(
        PDO::FETCH_ASSOC
    )
    as $sectionRemarkRow
) {

    $sectionKey =
        (string)(
            $sectionRemarkRow['section_key']
            ?? ''
        );

    if (
        isset(
            $researchSectionRemarkLabels[
                $sectionKey
            ]
        )
    ) {
        $sectionRemarks[
            $sectionKey
        ] = $sectionRemarkRow;
    }
}


/*
|--------------------------------------------------------------------------
| DOCUMENTS
|--------------------------------------------------------------------------
*/

$documentStmt =
    $pdo->prepare("
        SELECT

            document_id,
            document_type,
            uploaded_by,

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


$documentStmt->execute([
    $researchId
]);


$documents =
    $documentStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

// Payment-receipt records from older proposals remain in the database for
// historical retention, but are no longer part of the active document workflow.
$documents = array_values(array_filter(
    $documents,
    static function (array $document): bool {
        $type = strtolower(trim((string)($document['document_type'] ?? '')));
        $name = strtolower(trim((string)($document['original_name'] ?? '')));
        return !in_array($type, [
            'payment receipt', 'receipt', 'payment proof', 'proof of payment'
        ], true) && !in_array($name, [
            'payment receipt', 'payment receipt link',
            'receipt', 'receipt link', 'payment proof', 'proof of payment'
        ], true);
    }
));


/*
|--------------------------------------------------------------------------
| REGULAR RESEARCH: FINAL PROOF OF PUBLICATION / PRESENTATION
|--------------------------------------------------------------------------
| The RPS-issued permits are not the final step. Completion requires the
| faculty researcher to submit proof through research/progress.php.
*/
$regularFinalProofDocument = null;
if (strtoupper(trim((string)($research['submission_track'] ?? ''))) !== 'SELF_INITIATED') {
    foreach ($documents as $recordDocument) {
        if (in_array(strtolower(trim((string)($recordDocument['document_type'] ?? ''))), [
            'proof of publication',
            'proof of presentation',
            'proof of publication/presentation',
            'publication proof',
            'presentation proof'
        ], true)) {
            $regularFinalProofDocument = $recordDocument;
            break;
        }
    }
}
$regularFinalProofSubmitted = $regularFinalProofDocument !== null;


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
$viewerExternalUrl = null;
$viewerExternalPreviewUrl = null;
$viewerPhysicalPath = null;
$viewerExtension = null;
$viewerDownloadUrl = null;
$viewerInlineUrl = null;
$viewerPreviewText = null;
$viewerPreviewError = null;


function streamResearchUploadedFile(
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
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

        'jpg' =>
            'image/jpeg',

        'jpeg' =>
            'image/jpeg',

        'png' =>
            'image/png'

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


function extractResearchDocxText(
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
                'download',
                'visit',
                'visit_status'
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
                document_type,

                original_name,
                stored_name,
                file_path,

                file_extension,
                file_size,
                uploaded_at

            FROM research_documents

            WHERE document_id = ?
              AND research_id = ?

            LIMIT 1
        ");


    $fileStmt->execute([
        $documentId,
        $researchId
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


    // A linked document still uses this authenticated RPS/faculty document viewer.
    $externalUrl = researchExternalDocumentUrl((string)($fileDocument['file_path'] ?? ''));
    $storedAsLink = in_array(strtolower((string)($fileDocument['file_extension'] ?? '')), ['url', 'link'], true);
    if ($storedAsLink || $externalUrl !== null) {
        if ($externalUrl === null) {
            http_response_code(400);
            exit('The stored document link is invalid. Ask the researcher to provide a valid HTTPS link.');
        }
        // Opening the link is recorded only for the current signed-in
        // reviewer and document. It is NOT proof the file was read.
        $documentVisitKey = (int)$user['user_id'] . ':'
            . $researchId . ':' . (int)$fileDocument['document_id'];

        if ($fileMode === 'visit') {
            $_SESSION['rems_document_visits'][$documentVisitKey] = time();
            header('Cache-Control: no-store');
            header('Location: ' . $externalUrl, true, 302);
            exit;
        }

        if ($fileMode === 'visit_status') {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode([
                'visited' => !empty($_SESSION['rems_document_visits'][$documentVisitKey])
            ]);
            exit;
        }

        if ($fileMode !== 'view') {
            header('Location: ' . $externalUrl, true, 302);
            exit;
        }
        $viewerDocument = $fileDocument;
        $viewerExtension = 'url';
        $viewerExternalUrl = $externalUrl;
        $viewerExternalPreviewUrl = researchExternalDocumentPreviewUrl($externalUrl);
        $viewerDownloadUrl = $externalUrl;
    } else {
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
            'uploads/research/'
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

        streamResearchUploadedFile(
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
            !in_array(
                $extension,
                [
                    'pdf',
                    'jpg',
                    'jpeg',
                    'png'
                ],
                true
            )
        ) {

            http_response_code(415);

            exit(
                'Inline preview is available for PDF and image files only.'
            );
        }


        streamResearchUploadedFile(
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
        '/research/view.php?id=' .
        $researchId .
        '&document_id=' .
        $documentId .
        '&file_mode=download';

    $viewerInlineUrl =
        BASE_URL .
        '/research/view.php?id=' .
        $researchId .
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
            extractResearchDocxText(
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
    } // End local file processing; linked documents need no physical file.
}




/*
|--------------------------------------------------------------------------
| VIEW-ONLY DOCUMENT REVIEW CONFIRMATION
|--------------------------------------------------------------------------
| Confirmation records an acknowledgement by the current authorized viewer.
| It does NOT advance proposal status, replace any RPS processing decision,
| or modify the existing research workflow buttons.
| Google Drive iframe scrolling is cross-origin and cannot be inspected by
| this page; the reader must explicitly attest after checking the preview.
*/

// External files are reviewed on their provider website via Visit Link.
// Confirmation requires that visit, a scroll-to-end, and an explicit attestation.
$viewerReviewable = $viewerDocument !== null && (
    $viewerExternalUrl !== null
        ? true
        : in_array((string)$viewerExtension, ['pdf', 'jpg', 'jpeg', 'png'], true)
          || ($viewerExtension === 'docx' && $viewerPreviewText !== null)
);
$viewerExternalVisitRecorded = $viewerExternalUrl !== null
    && !empty($_SESSION['rems_document_visits'][
        (int)$user['user_id'] . ':' . $researchId . ':' . (int)$viewerDocument['document_id']
    ]);
$viewerReviewTableAvailable = false;
$viewerAlreadyConfirmed = false;
$viewerConfirmedAt = null;

if ($viewerDocument !== null) {
    try {
        // Independent audit table: no changes to research workflow tables.
        $pdo->exec("CREATE TABLE IF NOT EXISTS research_document_view_confirmations (
            document_id BIGINT UNSIGNED NOT NULL,
            research_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (document_id, user_id),
            KEY idx_document_confirm_research (research_id),
            KEY idx_document_confirm_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $viewerReviewTableAvailable = true;

        $confirmedStmt = $pdo->prepare("SELECT confirmed_at
            FROM research_document_view_confirmations
            WHERE document_id = ? AND user_id = ? LIMIT 1");
        $confirmedStmt->execute([
            (int)$viewerDocument['document_id'],
            (int)$user['user_id']
        ]);
        $viewerConfirmedAt = $confirmedStmt->fetchColumn() ?: null;
        $viewerAlreadyConfirmed = $viewerConfirmedAt !== null;
    } catch (Throwable $reviewTableError) {
        error_log('ASU-REMS document confirmation setup error: ' . $reviewTableError->getMessage());
    }
}

if (
    $viewerDocument !== null
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'confirm_document_review'
) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } elseif (!$viewerReviewTableAvailable || !$viewerReviewable) {
        $errors[] = 'This document is not available for review confirmation.';
    } elseif ($viewerExternalUrl !== null && !$viewerExternalVisitRecorded) {
        $errors[] = 'Please use Visit Link to open the document before confirming.';
    } elseif (($_POST['review_attested'] ?? '') !== '1') {
        $errors[] = 'Please review the document and acknowledge it before confirming.';
    } else {
        try {
            $saveConfirmation = $pdo->prepare("INSERT INTO research_document_view_confirmations
                (document_id, research_id, user_id, confirmed_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE confirmed_at = confirmed_at");
            $saveConfirmation->execute([
                (int)$viewerDocument['document_id'],
                $researchId,
                (int)$user['user_id']
            ]);
            redirect(BASE_URL . '/research/view.php?id=' . $researchId
                . '&document_id=' . (int)$viewerDocument['document_id']
                . '&file_mode=view&review_confirmed=1');
        } catch (Throwable $reviewSaveError) {
            error_log('ASU-REMS document confirmation save error: ' . $reviewSaveError->getMessage());
            $errors[] = 'Unable to save document confirmation. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| DOCUMENT CHECKS
|--------------------------------------------------------------------------
*/

$hasProposalDocument = false;
$hasEthicsCertificateDocument = false;
$hasRevisedProposalDocument = false;
$hasUpeCertificate1Document = false;
$hasUpeCertificate2Document = false;
$hasSelfInitiatedCompletedResearchDocument = false;
$hasSelfInitiatedTerminalReportDocument = false;
$hasSelfInitiatedPublicationProofDocument = false;

$proposalDocument = null;
$ethicsCertificateDocument = null;
$upeCertificate1Document = null;
$upeCertificate2Document = null;
$selfInitiatedCompletedResearchDocument = null;
$selfInitiatedTerminalReportDocument = null;
$selfInitiatedPublicationProofDocument = null;

foreach (
    $documents
    as
    $document
) {

    $documentType =
        strtolower(
            trim(
                (string)(
                    $document['document_type']
                    ?? ''
                )
            )
        );

    if (
        in_array(
            $documentType,
            [
                'proposal',
                'manuscript',
                'research manuscript',
                'research proposal'
            ],
            true
        )
    ) {

        $hasProposalDocument = true;

        if ($proposalDocument === null) {
            $proposalDocument = $document;
        }
    }


    if (
        in_array(
            $documentType,
            [
                'urec/iacuc certificate',
                'urec / iacuc certificate',
                'urec-iacuc certificate',
                'urec/iacuc result',
                'urec / iacuc result',
                'ethics certificate',
                'ethics result'
            ],
            true
        )
    ) {

        $hasEthicsCertificateDocument = true;

        if ($ethicsCertificateDocument === null) {
            $ethicsCertificateDocument = $document;
        }
    }


    if (
        $documentType
        === 'upe certificate 1'
    ) {

        $hasUpeCertificate1Document = true;

        if ($upeCertificate1Document === null) {
            $upeCertificate1Document = $document;
        }
    }


    if (
        $documentType
        === 'upe certificate 2'
    ) {

        $hasUpeCertificate2Document = true;

        if ($upeCertificate2Document === null) {
            $upeCertificate2Document = $document;
        }
    }


    if (
        $documentType
        === 'self-initiated completed research'
    ) {

        $hasSelfInitiatedCompletedResearchDocument = true;

        if ($selfInitiatedCompletedResearchDocument === null) {
            $selfInitiatedCompletedResearchDocument = $document;
        }
    }


    if (
        in_array(
            $documentType,
            [
                'self-initiated terminal report',
                'self initiated terminal report'
            ],
            true
        )
    ) {

        $hasSelfInitiatedTerminalReportDocument = true;

        if ($selfInitiatedTerminalReportDocument === null) {
            $selfInitiatedTerminalReportDocument = $document;
        }
    }


    if (
        in_array(
            $documentType,
            [
                'self-initiated proof of publication/presentation',
                'self initiated proof of publication/presentation',
                'self-initiated proof of publication / presentation',
                'self initiated proof of publication / presentation',
                'proof of publication',
                'proof of presentation',
                'self-initiated publication',
                'self initiated publication',
                'publication',
                'publication file'
            ],
            true
        )
    ) {

        $hasSelfInitiatedPublicationProofDocument = true;

        if ($selfInitiatedPublicationProofDocument === null) {
            $selfInitiatedPublicationProofDocument = $document;
        }
    }


    if (
        $documentType
        === 'revised proposal'
    ) {

        $hasRevisedProposalDocument = true;
    }
}

// Shared revision document evidence for both Faculty Submit and RPS review.
// Using one resolver prevents a successful Submit Revision from losing its
// View Revised Manuscript / Proceed controls because of a different lookup.
$currentUrrcRevisionRound = (($research['workflow_status'] ?? '') === 'URRC_REVISION_REQUIRED')
    ? remsRevisionRoundIdentity($pdo, $researchId, 'URRC') : null;
$currentInhouseRevisionRound = (($research['workflow_status'] ?? '') === 'INHOUSE_REVISION_REQUIRED')
    ? remsRevisionRoundIdentity($pdo, $researchId, 'INHOUSE') : null;

$currentUrrcRevisionDocument = remsResearchRevisionDocumentForStage(
    $pdo, $researchId, $research, $documents, 'URRC'
);
$currentInhouseRevisionDocument = remsResearchRevisionDocumentForStage(
    $pdo, $researchId, $research, $documents, 'INHOUSE'
);
$hasCurrentUrrcRevisionDocument = $currentUrrcRevisionDocument !== null;
$hasCurrentInhouseRevisionDocument = $currentInhouseRevisionDocument !== null;
$canManuallyResubmit = match ((string)($research['workflow_status'] ?? '')) {
    'INHOUSE_REVISION_REQUIRED' => $hasCurrentInhouseRevisionDocument,
    'URRC_REVISION_REQUIRED' => $hasCurrentUrrcRevisionDocument,
    default => $hasRevisedProposalDocument
};

$rpsUrrcSubmittedRevisedDocument = $currentUrrcRevisionDocument;
$rpsInhouseSubmittedRevisedDocument = $currentInhouseRevisionDocument;
$latestInhouseRevisedDocument = $rpsInhouseSubmittedRevisedDocument;

$hasRequiredPhaseOneDocuments =
    $hasProposalDocument;

usort(
    $documents,
    static function (array $first, array $second): int {

        $priority = [
            'proposal' => 1,
            'manuscript' => 1,
            'research manuscript' => 1,
            'research proposal' => 1,
            'urec/iacuc certificate' => 3,
            'urec / iacuc certificate' => 3,
            'urec-iacuc certificate' => 3,
            'urec/iacuc result' => 3,
            'urec / iacuc result' => 3,
            'ethics certificate' => 3,
            'ethics result' => 3,
            'revised proposal' => 4
        ];

        $firstType =
            strtolower(
                trim(
                    (string)(
                        $first['document_type']
                        ?? ''
                    )
                )
            );

        $secondType =
            strtolower(
                trim(
                    (string)(
                        $second['document_type']
                        ?? ''
                    )
                )
            );

        $firstPriority =
            $priority[$firstType]
            ?? 9;

        $secondPriority =
            $priority[$secondType]
            ?? 9;

        if ($firstPriority !== $secondPriority) {
            return $firstPriority <=> $secondPriority;
        }

        return
            (int)($second['document_id'] ?? 0)
            <=>
            (int)($first['document_id'] ?? 0);
    }
);


/*
|--------------------------------------------------------------------------
| SELF-INITIATED UPLOADS: SESSION RECORD OF EYE-BUTTON LINK VISITS
|--------------------------------------------------------------------------
| Records a deliberate eye-button click for the authenticated faculty user.
| This confirms that a link was opened, not that a third-party page loaded
| or that the document itself was fully read.
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'confirm_self_initiated_document_link_view'
) {
    header('Content-Type: application/json; charset=utf-8');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid request. Refresh and try again.']);
        exit;
    }

    if (($user['role'] ?? '') !== 'faculty'
        || (int)($research['researcher_id'] ?? 0) !== (int)($user['user_id'] ?? 0)
        || (int)($research['is_archived'] ?? 0) === 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This document link is not available for viewing.']);
        exit;
    }

    $viewAction = (string)($_POST['upload_action'] ?? '');
    $linkFields = [
        'upload_upe_certificate' => 'upe_certificate_link',
        'upload_self_initiated_completed_research' => 'self_initiated_completed_research_link',
        'upload_self_initiated_terminal_report' => 'self_initiated_terminal_report_link',
        'upload_self_initiated_publication_proof' => 'self_initiated_publication_proof_link'
    ];
    if (!isset($linkFields[$viewAction])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid document step.']);
        exit;
    }

    $slot = $viewAction === 'upload_upe_certificate'
        ? (int)($_POST['upe_slot'] ?? 0) : 0;
    if ($viewAction === 'upload_upe_certificate' && !in_array($slot, [1, 2], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid certificate slot.']);
        exit;
    }

    $viewUrl = researchExternalDocumentUrl((string)($_POST[$linkFields[$viewAction]] ?? ''));
    if ($viewUrl === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Enter a valid HTTPS document link first.']);
        exit;
    }

    $viewKey = $researchId . ':' . (int)$user['user_id'] . ':' . $viewAction . ':' . $slot;
    $_SESSION['rems_self_initiated_link_views'][$viewKey] = hash('sha256', $viewUrl);
    echo json_encode(['success' => true]);
    exit;
}

/*
|--------------------------------------------------------------------------
| RPS COMMENTS: SAVE IMMEDIATELY, INDEPENDENT OF WORKFLOW SUBMISSION
|--------------------------------------------------------------------------
| Private to the assigned faculty researcher and RPS reviewer. This action
| never changes research status, pathway, or workflow history.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (string)($_POST['action'] ?? '') === 'save_section_comment') {

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');

    $respond = static function (int $code, bool $success, string $message, array $extra = []): never {
        http_response_code($code);
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (($user['role'] ?? '') !== 'rps') {
        $respond(403, false, 'Only Research Processing Staff can add comments.');
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $respond(403, false, 'Invalid request. Refresh the page and try again.');
    }
    if (researchSectionRemarksAreLocked($research)) {
        $respond(409, false,
            'Comments can be added only after the researcher selects a pathway, before initial processing continues.');
    }

    $commentSection = strtolower(trim((string)($_POST['section_key'] ?? '')));
    $commentText = trim((string)($_POST['remark_text'] ?? ''));

    if (!isset($researchSectionRemarkLabels[$commentSection])) {
        $respond(422, false, 'Invalid research section.');
    }
    if ($commentText === '') {
        $respond(422, false, 'Please enter a comment before saving.');
    }
    if (mb_strlen($commentText) > 5000) {
        $respond(422, false, 'Each comment must not exceed 5,000 characters.');
    }

    try {
        // Lock the proposal so a pathway or processing change cannot race
        // with an independently saved comment.
        $pdo->beginTransaction();
        $commentProposalStmt = $pdo->prepare('SELECT researcher_id, workflow_status,
            submission_track, is_archived FROM research_proposals
            WHERE research_id = ? LIMIT 1 FOR UPDATE');
        $commentProposalStmt->execute([$researchId]);
        $commentProposal = $commentProposalStmt->fetch(PDO::FETCH_ASSOC);
        if (!$commentProposal || researchSectionRemarksAreLocked($commentProposal)) {
            throw new DomainException('This proposal is no longer open for RPS comments. Refresh the page.');
        }
        $commentSaveStmt = $pdo->prepare('INSERT INTO research_section_remarks
            (research_id, section_key, remark_text, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE remark_text = VALUES(remark_text),
                created_by = VALUES(created_by), updated_at = NOW()');
        $commentSaveStmt->execute([
            $researchId, $commentSection, $commentText, (int)$user['user_id']
        ]);
        $pdo->commit();

        // Notify only the researcher who owns this proposal; no Admin/RPS
        // broadcast, and no comment text is exposed in notifications.
        try {
            createNotification(
                $pdo, (int)$commentProposal['researcher_id'],
                'New RPS comment on your research',
                'Research Processing Staff saved a private comment on '
                    . $researchSectionRemarkLabels[$commentSection]
                    . ' for your research proposal.',
                'research', $researchId,
                BASE_URL . '/research/view.php?id=' . $researchId . '#research-content'
            );
        } catch (Throwable $notificationError) {
            error_log('RPS comment notification warning: ' . $notificationError->getMessage());
        }

        $respond(200, true, 'Comment saved.', [
            'section_key' => $commentSection,
            'saved_at' => date('M d, Y g:i A')
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('RPS immediate comment save failed: ' . $e->getMessage());
        $respond($e instanceof DomainException ? 409 : 500, false,
            $e instanceof DomainException ? $e->getMessage() : 'Unable to save the comment. Please try again.');
    }
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
        $_POST['action'] ?? '';


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

    // All four self-initiated submissions require a per-step eye-button visit.
    // This also rejects direct POSTs that bypass the disabled submit button.
    $guardedLinks = [
        'upload_upe_certificate' => 'upe_certificate_link',
        'upload_self_initiated_completed_research' => 'self_initiated_completed_research_link',
        'upload_self_initiated_terminal_report' => 'self_initiated_terminal_report_link',
        'upload_self_initiated_publication_proof' => 'self_initiated_publication_proof_link'
    ];
    if (!$errors && isset($guardedLinks[$action])) {
        $viewSlot = $action === 'upload_upe_certificate'
            ? (int)($_POST['upe_slot'] ?? 0) : 0;
        $submittedUrl = researchExternalDocumentUrl(
            (string)($_POST[$guardedLinks[$action]] ?? '')
        );
        $viewKey = $researchId . ':' . (int)$user['user_id'] . ':' . $action . ':' . $viewSlot;
        $viewHash = $_SESSION['rems_self_initiated_link_views'][$viewKey] ?? null;
        if ($submittedUrl === null || !is_string($viewHash)
            || !hash_equals($viewHash, hash('sha256', $submittedUrl))) {
            $errors[] = 'You must click the eye button to view this document link before submitting.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FACULTY SUBMIT DRAFT
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'submit_for_review'
        &&
        !$errors
    ) {

        if (
            $user['role'] !== 'faculty'
        ) {

            $errors[] =
                'Only the faculty researcher may submit this proposal.';
        }


        if (
            (int)$research['researcher_id']
            !==
            (int)$user['user_id']
        ) {

            $errors[] =
                'You are not authorized to submit this proposal.';
        }


        if (
            $research['status']
            !== 'Draft'
        ) {

            $errors[] =
                'Only Draft proposals can be submitted for review.';
        }


        if (
            !$hasProposalDocument
        ) {

            $errors[] =
                'A manuscript document is required before submission.';
        }




        if (!$errors) {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | MANUSCRIPT NUMBER
                |--------------------------------------------------------------------------
                */

                $manuscriptNo =
                    ensureResearchManuscriptNumber(
                        $pdo,
                        $researchId
                    );


                /*
                |--------------------------------------------------------------------------
                | UPDATE MASTER RESEARCH RECORD
                |--------------------------------------------------------------------------
                */

                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = 'Submitted',

                            workflow_status =
                                'AWAITING_PATHWAY_SELECTION',

                            workflow_stage =
                                'PATHWAY_SELECTION',

                            is_archived = 0,

                            archived_reason = NULL,

                            archived_at = NULL,

                            submitted_at = NOW(),

                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND status = 'Draft'
                    ");


                $update->execute([

                    $researchId,

                    (int)$user[
                        'user_id'
                    ]

                ]);


                if (
                    $update->rowCount()
                    !== 1
                ) {

                    throw new RuntimeException(
                        'Unable to update proposal status.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | STATUS HISTORY
                |--------------------------------------------------------------------------
                */

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
                            'Draft',
                            'Submitted',
                            ?
                        )
                    ");


                $history->execute([

                    $researchId,

                    (int)$user[
                        'user_id'
                    ],

                    'Research proposal submitted. Awaiting faculty pathway selection before review processing.'

                ]);


                /*
                |--------------------------------------------------------------------------
                | NOTIFY RPS + ADMIN
                |--------------------------------------------------------------------------
                */

                notifyUsersByRole(
                    $pdo,

                    [
                        'rps',
                        'admin'
                    ],

                    'New research proposal submitted',

                    fullName($user)
                    .
                    ' submitted research proposal '
                    .
                    $manuscriptNo
                    .
                    ': '
                    .
                    $research[
                        'research_title'
                    ],

                    'research',

                    $researchId,

                    BASE_URL
                    .
                    '/research/view.php?id='
                    .
                    $researchId,

                    (int)$user[
                        'user_id'
                    ]
                );


                $pdo->commit();


                setFlash(
                    'success',
                    'Research proposal submitted successfully. Manuscript No.: '
                    .
                    $manuscriptNo
                );


                redirect(
                    BASE_URL
                    .
                    '/research/view.php?id='
                    .
                    $researchId
                );


            } catch (
                Throwable $e
            ) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }


                error_log(
                    'Research workflow submission error: '
                    .
                    $e->getMessage()
                );


                $errors[] =
                    'Research Submission Error: ' .
                    $e->getMessage();
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STEP 2 — PATHWAY SELECTION FOR NEWLY SUBMITTED RESEARCH ONLY
    |--------------------------------------------------------------------------
    | Keep legacy SUBMITTED and ETHICS_COMPLETED records on their current route.
    | Neither selection starts ethics/URRC review or generates a certificate.
    | The selected staging state prevents accidentally skipping the reordered
    | UREC/IACUC review while the remaining workflow changes are introduced.
    */
    elseif (
        in_array($action, ['select_path_for_urrc', 'select_path_self_initiated'], true)
        && !$errors
    ) {
        if (($user['role'] ?? '') !== 'faculty'
            || (int)$research['researcher_id'] !== (int)$user['user_id']) {
            $errors[] = 'Only the faculty researcher who owns this proposal may select its pathway.';
        }

        if (($research['workflow_status'] ?? '') !== 'AWAITING_PATHWAY_SELECTION'
            || ($research['status'] ?? '') !== 'Submitted') {
            $errors[] = 'Pathway selection is only available for newly submitted proposals awaiting a pathway.';
        }

        if ((int)($research['is_archived'] ?? 0) === 1) {
            $errors[] = 'Archived research cannot select a pathway.';
        }

        if (!$errors) {
            $chooseSelfInitiated = ($action === 'select_path_self_initiated');
            $selectedTrack = $chooseSelfInitiated ? 'SELF_INITIATED' : 'REGULAR';
            $selectedStatus = $chooseSelfInitiated
                ? 'SELF_INITIATED_UPE_REQUIRED'
                : 'FOR_URRC_PATH_SELECTED';
            $selectedStage = $chooseSelfInitiated ? 'UPE_BEFORE_ETHICS' : 'INITIAL_RPS_REVIEW';
            $selectedLabel = $chooseSelfInitiated ? 'Self-Initiated' : 'For URRC';
            // Both paths remain Submitted until the appropriate processing step begins.
            $selectedResearchStatus = 'Submitted';

            try {
                $pdo->beginTransaction();
                $selectPathStmt = $pdo->prepare("
                    UPDATE research_proposals
                    SET status = ?,
                        submission_track = ?,
                        workflow_status = ?,
                        workflow_stage = ?,
                        workflow_updated_at = NOW()
                    WHERE research_id = ?
                      AND researcher_id = ?
                      AND status = 'Submitted'
                      AND workflow_status = 'AWAITING_PATHWAY_SELECTION'
                      AND is_archived = 0
                ");
                $selectPathStmt->execute([
                    $selectedResearchStatus,
                    $selectedTrack,
                    $selectedStatus,
                    $selectedStage,
                    $researchId,
                    (int)$user['user_id']
                ]);
                if ($selectPathStmt->rowCount() !== 1) {
                    throw new RuntimeException('Pathway selection was already processed.');
                }

                $historyStmt = $pdo->prepare("
                    INSERT INTO research_status_history
                    (research_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, 'Submitted', ?, ?)
                ");
                $historyStmt->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $selectedResearchStatus,
                    'Faculty selected the ' . $selectedLabel . ' pathway after proposal submission.'
                ]);
                $pdo->commit();

                try {
                    notifyUsersByRole(
                        $pdo,
                        ['rps', 'admin'],
                        'Research pathway selected',
                        fullName($user) . ' selected ' . $selectedLabel . ' for research '
                            . ($research['manuscript_no'] ?: '#' . $researchId) . '.',
                        'research',
                        $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $notificationError) {
                    error_log('Pathway selection notification warning: ' . $notificationError->getMessage());
                }

                setFlash('success', $chooseSelfInitiated
                    ? 'Self-Initiated pathway selected. Submit both UPE certificate links next.'
                    : 'For URRC pathway selected. Awaiting initial RPS processing before URRC Review.');
                redirect(BASE_URL . '/research/view.php?id=' . $researchId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Pathway selection error: ' . $e->getMessage());
                $errors[] = 'Unable to select the research pathway. Please try again.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RPS INITIAL INTAKE — FORWARD SELECTED FOR URRC RESEARCH
    |--------------------------------------------------------------------------
    | Selection alone must NOT start URRC committee review.
    | Keep the earlier legacy review actions on their original paths.
    */
    elseif ($action === 'forward_to_urrc' && !$errors) {
        if (($user['role'] ?? '') !== 'rps') {
            $errors[] = 'Only Research Processing Staff may forward research to URRC.';
        }
        if ((int)($research['is_archived'] ?? 0) === 1) {
            $errors[] = 'Archived research cannot be forwarded.';
        }
        if (!researchUrrcAwaitingRpsIntake($pdo, $researchId, $research)) {
            $errors[] = 'This proposal is not awaiting initial RPS processing.';
        }
        if (!$hasRequiredPhaseOneDocuments) {
            $errors[] = 'A manuscript document is required for initial processing.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $oldStatus = (string)($research['status'] ?? 'Submitted');
                $previousWorkflow = (string)$research['workflow_status'];
                $forward = $pdo->prepare("UPDATE research_proposals
                    SET status = 'Under Review',
                        submission_track = 'REGULAR',
                        workflow_status = 'FOR_URRC',
                        workflow_stage = 'URRC',
                        workflow_updated_at = NOW()
                    WHERE research_id = ? AND workflow_status = ? AND is_archived = 0");
                $forward->execute([$researchId, $previousWorkflow]);
                if ($forward->rowCount() !== 1) {
                    throw new RuntimeException('The research could not be forwarded to URRC.');
                }

                $history = $pdo->prepare("INSERT INTO research_status_history
                    (research_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, ?, 'Under Review', ?)");
                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $oldStatus,
                    'RPS forwarded selected For URRC research from New Submissions to URRC Review.'
                ]);
                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    'Research forwarded to URRC Review',
                    'Your research "' . $research['research_title'] . '" has passed initial RPS intake and is now for URRC Review.',
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );
                $pdo->commit();
                setFlash('success', 'Initial RPS processing completed. Research forwarded to URRC Review.');
                redirect(BASE_URL . '/research/view.php?id=' . $researchId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Initial RPS to URRC forwarding error: ' . $e->getMessage());
                $errors[] = 'Unable to forward the research to URRC. Please try again.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FACULTY CHOOSES SELF-INITIATED AFTER 180-DAY IN-HOUSE DEADLINE
    |--------------------------------------------------------------------------
    | The 180-day checker archives an overdue In-House revision first.
    | Only its faculty owner may re-open that archived record by choosing
    | the existing Self-Initiated UPE -> UREC/IACUC pathway.
    | The original archival event stays in the immutable status history.
    */
    elseif ($action === 'proceed_to_self_initiated_from_inhouse' && !$errors) {
        if (($user['role'] ?? '') !== 'faculty'
            || (int)$research['researcher_id'] !== (int)$user['user_id']) {
            $errors[] = 'Only the research owner may choose Self-Initiated.';
        }
        if (($research['status'] ?? '') !== 'Archived'
            || ($research['workflow_status'] ?? '') !== 'INHOUSE_DID_NOT_REVISE'
            || ($research['submission_track'] ?? '') !== 'REGULAR'
            || (int)($research['is_archived'] ?? 0) !== 1
            || !researchSelectedEarlyPath($pdo, $researchId, 'REGULAR')) {
            $errors[] = 'Proceed to Self-Initiated becomes available only after the 180-day In-House revision deadline has expired.';
        }
        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT research_id, status, submission_track, workflow_status, is_archived FROM research_proposals WHERE research_id = ? FOR UPDATE');
                $lock->execute([$researchId]);
                $current = $lock->fetch(PDO::FETCH_ASSOC);
                $deadline = remsInhouseRevisionDeadline($pdo, $researchId);
                if (!$current || $current['status'] !== 'Archived'
                    || $current['workflow_status'] !== 'INHOUSE_DID_NOT_REVISE'
                    || $current['submission_track'] !== 'REGULAR'
                    || (int)$current['is_archived'] !== 1
                    || !$deadline || (int)$deadline['expired'] !== 1
                    || !researchSelectedEarlyPath($pdo, $researchId, 'REGULAR')) {
                    throw new RuntimeException('The 180-day deadline has not expired or the archived research is not eligible.');
                }

                $switch = $pdo->prepare("
                    UPDATE research_proposals
                    SET status = 'Submitted',
                        submission_track = 'SELF_INITIATED',
                        workflow_status = 'SELF_INITIATED_UPE_REQUIRED',
                        workflow_stage = 'UPE_BEFORE_ETHICS',
                        is_archived = 0,
                        archived_reason = NULL,
                        archived_at = NULL,
                        workflow_updated_at = NOW()
                    WHERE research_id = ? AND researcher_id = ?
                      AND status = 'Archived'
                      AND workflow_status = 'INHOUSE_DID_NOT_REVISE'
                      AND submission_track = 'REGULAR'
                      AND is_archived = 1
                ");
                $switch->execute([$researchId, (int)$user['user_id']]);
                if ($switch->rowCount() !== 1) {
                    throw new RuntimeException('Unable to continue the archived research as Self-Initiated.');
                }

                $history = $pdo->prepare("
                    INSERT INTO research_status_history
                    (research_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, 'Archived', 'Submitted', ?)
                ");
                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    'Faculty chose Self-Initiated after the 180-day In-House revision deadline. The prior automatic archive remains recorded in research_status_history.'
                ]);
                $pdo->commit();

                try {
                    notifyUsersByRole(
                        $pdo, ['rps', 'admin'], 'Research continued as Self-Initiated',
                        fullName($user) . ' chose Self-Initiated after the 180-day In-House revision deadline for '
                            . ($research['manuscript_no'] ?: '#' . $researchId) . '.',
                        'research', $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $notifyError) {
                    error_log('In-House to Self-Initiated notification warning: ' . $notifyError->getMessage());
                }
                setFlash('success', 'Self-Initiated pathway selected. Submit both UPE certificate links next.');
                redirect(BASE_URL . '/research/view.php?id=' . $researchId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('In-House to Self-Initiated transition error: ' . $e->getMessage());
                $errors[] = 'Unable to proceed to Self-Initiated. The 180-day deadline must have expired and the record must be archived.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FACULTY — FINAL SUBMIT OF A SAVED URRC / IN-HOUSE REVISION
    |--------------------------------------------------------------------------
    | Both stages share exactly one transaction and one document resolver.
    | Edit & Save never advances status; a successful Submit Revision does.
    | Errors are logged with a reference AND shown beside the buttons below.
    */
    elseif (in_array($action, ['submit_urrc_revision', 'submit_inhouse_revision'], true)
            && !$errors) {
        $submitIsUrrc = $action === 'submit_urrc_revision';
        $submitStage = $submitIsUrrc ? 'URRC' : 'INHOUSE';
        $submitLabel = $submitIsUrrc ? 'URRC' : 'In-House';
        $submitRequired = $submitIsUrrc
            ? 'URRC_REVISION_REQUIRED' : 'INHOUSE_REVISION_REQUIRED';
        $submitAccepted = $submitIsUrrc
            ? 'URRC_REVISED_SUBMITTED' : 'INHOUSE_REVISED_SUBMITTED';
        $submitHistoryRemarks = $submitIsUrrc
            ? 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.'
            : 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.';
        // The submitted document ID and review-round key are permanently saved
        // in the very same transaction as the workflow transition. RPS reads this
        // exact document, rather than guessing from file names/timestamps.
        $submitDocument = $submitIsUrrc
            ? $currentUrrcRevisionDocument : $currentInhouseRevisionDocument;
        $submitRound = $submitIsUrrc
            ? $currentUrrcRevisionRound : $currentInhouseRevisionRound;
        $submitRef = ($submitIsUrrc ? 'URS-' : 'IHS-')
            . strtoupper(bin2hex(random_bytes(3)));

        if (($user['role'] ?? '') !== 'faculty'
            || (int)($research['researcher_id'] ?? 0) !== (int)($user['user_id'] ?? 0)) {
            $errors[] = 'Only the faculty researcher who owns this manuscript may submit the revision.';
        } elseif ((int)($research['is_archived'] ?? 0) !== 0) {
            $errors[] = 'This manuscript is archived; its revision cannot be submitted.';
        } elseif (($research['workflow_status'] ?? '') === $submitAccepted) {
            // Safe duplicate POST: do not insert duplicate history/notifications.
            setFlash('success', $submitLabel . ' revision is already submitted to RPS.');
            redirect(BASE_URL . '/research/view.php?id=' . $researchId . '#revision-submitted');
            exit;
        } elseif (($research['status'] ?? '') !== 'For Revision'
            || ($research['workflow_status'] ?? '') !== $submitRequired) {
            $errors[] = 'The current ' . $submitLabel . ' revision is no longer open for submission.';
        } elseif ($submitDocument === null || $submitRound === null) {
            $errors[] = 'There is no saved revised manuscript for this ' . $submitLabel
                . ' decision. Use Edit & Save first, then Submit Revision.';
        }

        // Use the same stage-specific deadlines used by Edit & Save.
        if (!$errors) {
            $deadline = $submitIsUrrc
                ? remsUrrcRevisionDeadline($pdo, $researchId)
                : remsInhouseRevisionDeadline($pdo, $researchId);
            if ((!$submitIsUrrc || $isEarlyUrrcRevision) && !$deadline) {
                $errors[] = $submitLabel . ' revision deadline is unavailable. The submission was not recorded.';
            } elseif ($deadline && (int)($deadline['expired'] ?? 0) === 1) {
                $errors[] = $submitLabel . ' revision deadline has expired.';
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT researcher_id, status, workflow_status, is_archived
                    FROM research_proposals WHERE research_id = ? FOR UPDATE');
                $lock->execute([$researchId]);
                $current = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$current || (int)$current['researcher_id'] !== (int)$user['user_id']
                    || (int)$current['is_archived'] !== 0
                    || (string)$current['status'] !== 'For Revision'
                    || (string)$current['workflow_status'] !== $submitRequired) {
                    throw new RuntimeException('Revision status changed before final submit.');
                }

                $lockedRound = remsRevisionRoundIdentity($pdo, $researchId, $submitStage);
                if (!$lockedRound || $lockedRound['key'] !== $submitRound['key']) {
                    throw new RuntimeException('Revision round changed since saving the link.');
                }
                $lockedDeadline = $submitIsUrrc
                    ? remsUrrcRevisionDeadline($pdo, $researchId)
                    : remsInhouseRevisionDeadline($pdo, $researchId);
                if ((!$submitIsUrrc || $isEarlyUrrcRevision) && !$lockedDeadline) {
                    throw new RuntimeException('Revision deadline record is not available.');
                }
                if ($lockedDeadline && (int)($lockedDeadline['expired'] ?? 0) === 1) {
                    throw new RuntimeException('Revision deadline expired before submit.');
                }

                // Verify the exact saved document selected by the shared resolver.
                // Never substitute an old URRC link for a new In-House revision.
                $documentStmt = $pdo->prepare('SELECT document_id, file_path
                    FROM research_documents WHERE document_id = ? AND research_id = ?
                      AND uploaded_by = ? AND document_type = ?
                      AND stored_name = ? AND file_path = ? LIMIT 1');
                $documentStmt->execute([
                    (int)$submitDocument['document_id'], $researchId, (int)$user['user_id'],
                    'Revised Proposal', (string)$submitDocument['stored_name'],
                    (string)$submitDocument['file_path']
                ]);
                $verifiedDocument = $documentStmt->fetch(PDO::FETCH_ASSOC);
                if (!$verifiedDocument || researchExternalDocumentUrl((string)$verifiedDocument['file_path']) === null) {
                    throw new RuntimeException('Saved revised document no longer matches the current database record.');
                }
                // Exact, immutable link between this RPS revision decision and
                // the Faculty's FINAL submitted document. Saved draft links from
                // earlier rounds or the other stage will never be substituted.
                $submitHistoryRemarks .= ' [REVISION_DOC_ID='
                    . (int)$verifiedDocument['document_id']
                    . ';ROUND=' . $lockedRound['key'] . ']';

                $update = $pdo->prepare('UPDATE research_proposals
                    SET status = ?, workflow_status = ?, workflow_stage = ?,
                        submitted_at = NOW(), workflow_updated_at = NOW()
                    WHERE research_id = ? AND researcher_id = ? AND status = ?
                      AND workflow_status = ? AND is_archived = 0');
                $update->execute([
                    'Submitted', $submitAccepted, $submitStage, $researchId,
                    (int)$user['user_id'], 'For Revision', $submitRequired
                ]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('The final submission did not update exactly one proposal.');
                }

                $history = $pdo->prepare('INSERT INTO research_status_history
                    (research_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, ?, ?, ?)');
                $history->execute([
                    $researchId, (int)$user['user_id'],
                    'For Revision', 'Submitted', $submitHistoryRemarks
                ]);

                // Check the database result, not merely whether execute() returned.
                $verify = $pdo->prepare('SELECT status, workflow_status FROM research_proposals
                    WHERE research_id = ? LIMIT 1');
                $verify->execute([$researchId]);
                $after = $verify->fetch(PDO::FETCH_ASSOC);
                if (!$after || (string)$after['status'] !== 'Submitted'
                    || (string)$after['workflow_status'] !== $submitAccepted) {
                    throw new RuntimeException('Database status verification failed after final submit.');
                }
                $pdo->commit();

                try {
                    notifyUsersByRole(
                        $pdo, ['rps', 'admin'], $submitLabel . ' revised manuscript submitted',
                        fullName($user) . ' submitted the revised ' . $submitLabel
                            . ' manuscript for "' . $research['research_title'] . '". RPS may now verify it.',
                        'research', $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $notifyError) {
                    // An optional notification must not roll back an already-recorded submit.
                    error_log('[ASU-REMS] ' . $submitRef . ' notification: ' . $notifyError->getMessage());
                }
                setFlash('success', $submitLabel
                    . ' revision successfully submitted to RPS for verification.');
                redirect(BASE_URL . '/research/view.php?id=' . $researchId . '#revision-submitted');
                exit;
            } catch (Throwable $submitError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[ASU-REMS] ' . $submitRef . ' ' . $submitStage
                    . ' final submit research_id=' . $researchId
                    . ' error=' . $submitError->getMessage());
                $errors[] = 'Submit Revision was NOT recorded. Reference: ' . $submitRef
                    . '. No need to Edit & Save again. Check the Apache error log for this reference.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FACULTY RESUBMIT REVISION
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'resubmit_revision'
        &&
        !$errors
    ) {

        if (
            $user['role'] !== 'faculty'
        ) {

            $errors[] =
                'Only the faculty researcher may resubmit this proposal.';
        }


        if (
            (int)$research['researcher_id']
            !==
            (int)$user['user_id']
        ) {

            $errors[] =
                'You are not authorized to resubmit this proposal.';
        }


        if (
            $research['status']
            !== 'For Revision'
        ) {

            $errors[] =
                'Only proposals marked For Revision can be resubmitted.';
        }

        if (in_array(($research['workflow_status'] ?? ''),
            ['URRC_REVISION_REQUIRED', 'INHOUSE_REVISION_REQUIRED'], true)) {
            $errors[] = 'Use Edit & Resubmit to submit a new revision document for this stage.';
        }

        if (!$canManuallyResubmit) {
            $errors[] = 'Please save the revised manuscript link for this review stage before resubmitting.';
        }


        if (!$errors) {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | DETERMINE DETAILED WORKFLOW DESTINATION
                |--------------------------------------------------------------------------
                */

                $currentWorkflowStatus =
                    (string)(
                        $research[
                            'workflow_status'
                        ]
                        ?? ''
                    );


                $newWorkflowStatus =
                    match (
                        $currentWorkflowStatus
                    ) {

                        'URRC_REVISION_REQUIRED' =>
                            'URRC_REVISED_SUBMITTED',

                        'INHOUSE_REVISION_REQUIRED' =>
                            'INHOUSE_REVISED_SUBMITTED',

                        default =>
                            'SUBMITTED'
                    };


                $newWorkflowStage =
                    match (
                        $newWorkflowStatus
                    ) {

                        'URRC_REVISED_SUBMITTED' =>
                            'URRC',

                        'INHOUSE_REVISED_SUBMITTED' =>
                            'INHOUSE',

                        default =>
                            'SUBMISSION'
                    };


                /*
                |--------------------------------------------------------------------------
                | UPDATE MASTER RECORD
                |--------------------------------------------------------------------------
                */

                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = 'Submitted',

                            workflow_status = ?,

                            workflow_stage = ?,

                            is_archived = 0,

                            archived_reason = NULL,

                            archived_at = NULL,

                            submitted_at = NOW(),

                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND status = 'For Revision'
                    ");


                $update->execute([

                    $newWorkflowStatus,

                    $newWorkflowStage,

                    $researchId,

                    (int)$user[
                        'user_id'
                    ]

                ]);


                if (
                    $update->rowCount()
                    !== 1
                ) {

                    throw new RuntimeException(
                        'Unable to resubmit revised proposal.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | STATUS HISTORY
                |--------------------------------------------------------------------------
                */

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
                            'For Revision',
                            'Submitted',
                            ?
                        )
                    ");


                $history->execute([

                    $researchId,

                    (int)$user[
                        'user_id'
                    ],

                    'Revised research proposal resubmitted to the Research Office for workflow processing.'

                ]);


                /*
                |--------------------------------------------------------------------------
                | NOTIFY RPS + ADMIN
                |--------------------------------------------------------------------------
                */

                notifyUsersByRole(
                    $pdo,

                    [
                        'rps',
                        'admin'
                    ],

                    'Revised research proposal resubmitted',

                    fullName($user)
                    .
                    ' resubmitted revised research proposal '
                    .
                    (
                        !empty(
                            $research[
                                'manuscript_no'
                            ]
                        )
                            ? $research[
                                'manuscript_no'
                            ]
                            : '#' . $researchId
                    )
                    .
                    ': '
                    .
                    $research[
                        'research_title'
                    ],

                    'research',

                    $researchId,

                    BASE_URL
                    .
                    '/research/view.php?id='
                    .
                    $researchId,

                    (int)$user[
                        'user_id'
                    ]
                );


                $pdo->commit();


                setFlash(
                    'success',
                    'Revised research proposal resubmitted successfully.'
                );


                redirect(
                    BASE_URL
                    .
                    '/research/view.php?id='
                    .
                    $researchId
                );


            } catch (
                Throwable $e
            ) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }


                error_log(
                    'Research workflow resubmission error: '
                    .
                    $e->getMessage()
                );


                $errors[] =
                    'Unable to resubmit the revised proposal. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RPS START UREC / IACUC PROCESSING
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'start_ethics_review'
        &&
        !$errors
    ) {

        if (
            ($user['role'] ?? '') !== 'rps'
        ) {

            $errors[] =
                'Only Research Processing Staff may start UREC/IACUC processing.';
        }


        $ethicsStartStatus = (string)($research['workflow_status'] ?? '');
        $ethicsAfterUpe = $ethicsStartStatus === 'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS';

        if (!in_array($ethicsStartStatus, [
            'SUBMITTED',
            'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS'
        ], true)) {
            $errors[] = 'UREC/IACUC processing is not available at the current workflow step.';
        }

        // For the new Self-Initiated path, the two links are mandatory before ethics.
        if ($ethicsAfterUpe && (
            ($research['submission_track'] ?? '') !== 'SELF_INITIATED'
            || !$hasUpeCertificate1Document || !$hasUpeCertificate2Document
        )) {
            $errors[] = 'Both UPE certificate links are required before UREC/IACUC processing.';
        }

        if (
            (int)($research['is_archived'] ?? 0)
            === 1
        ) {

            $errors[] =
                'Archived research cannot be processed.';
        }


        if (!$errors) {

            try {

                $pdo->beginTransaction();


                $oldStatus =
                    (string)$research['status'];


                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = 'Under Review',

                            workflow_status =
                                'FOR_ETHICS_REVIEW',

                            workflow_stage = ?,

                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND workflow_status = ?
                          AND is_archived = 0
                    ");


                // Keep the canonical ETHICS stage for compatibility with RPS queues.
                $update->execute([
                    'ETHICS',
                    $researchId,
                    $ethicsStartStatus
                ]);


                if (
                    $update->rowCount()
                    !== 1
                ) {

                    throw new RuntimeException(
                        'Unable to start UREC/IACUC processing.'
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
                            'Under Review',
                            ?
                        )
                    ");


                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $oldStatus,
                    'Research Processing Staff started UREC/IACUC processing.'
                ]);


                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    'Research moved to UREC/IACUC processing',
                    'Your research proposal "'
                    . $research['research_title']
                    . '" is now under UREC/IACUC processing by the Research Office.',
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );


                $pdo->commit();


                setFlash(
                    'success',
                    'UREC/IACUC processing started successfully.'
                );


                redirect(
                    BASE_URL
                    . '/research/view.php?id='
                    . $researchId
                );


            } catch (Throwable $e) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }


                error_log(
                    'Start UREC/IACUC Processing Error: '
                    . $e->getMessage()
                );


                $errors[] =
                    'Unable to start UREC/IACUC processing. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RPS SAVE UREC / IACUC RESULT
    |--------------------------------------------------------------------------
    |
    | RPS records the official UREC/IACUC classification only.
    | The certificate is generated by the system from the saved research data,
    | so RPS no longer needs to upload a certificate file.
    |
    */

    elseif (
        $action === 'save_ethics_result'
        &&
        !$errors
    ) {

        if (
            ($user['role'] ?? '') !== 'rps'
        ) {

            $errors[] =
                'Only Research Processing Staff may record the UREC/IACUC result.';
        }


        if (
            ($research['workflow_status'] ?? '')
            !== 'FOR_ETHICS_REVIEW'
        ) {

            $errors[] =
                'This research is not currently waiting for a UREC/IACUC result.';
        }


        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {

            $errors[] =
                'Archived research cannot be processed.';
        }


        $ethicsResult =
            strtoupper(
                trim(
                    (string)(
                        $_POST['ethics_result']
                        ?? ''
                    )
                )
            );


        $ethicsRemarks =
            trim(
                (string)(
                    $_POST['ethics_remarks']
                    ?? ''
                )
            );


        $allowedEthicsResults = [
            'EXEMPT',
            'EXPEDITED',
            'FULL_BOARD'
        ];


        if (
            !in_array(
                $ethicsResult,
                $allowedEthicsResults,
                true
            )
        ) {

            $errors[] =
                'Please select a valid UREC/IACUC classification.';
        }


        if (
            mb_strlen($ethicsRemarks) > 5000
        ) {

            $errors[] =
                'UREC/IACUC remarks must not exceed 5,000 characters.';
        }


        if (!$errors) {

            try {

                $pdo->beginTransaction();


                $resultLabel =
                    match ($ethicsResult) {
                        'EXEMPT' => 'Exempt',
                        'EXPEDITED' => 'Expedited',
                        'FULL_BOARD' => 'Full-board',
                        default => $ethicsResult
                    };


                // The Step 2 audit marker identifies the reordered routes.
                // The old ETHICS-first workflow keeps its original transition.
                $ethicsEntryStage = (string)($research['workflow_stage'] ?? '');
                $ethicsAfterUpe = ($research['submission_track'] ?? '') === 'SELF_INITIATED'
                    && researchSelectedEarlyPath($pdo, $researchId, 'SELF_INITIATED');
                $inhousePassedStmt = $pdo->prepare("SELECT 1 FROM research_evaluations
                    WHERE research_id = ? AND decision = 'In-House Passed' LIMIT 1");
                $inhousePassedStmt->execute([$researchId]);
                $ethicsAfterInhouse = !$ethicsAfterUpe
                    && ($research['submission_track'] ?? '') !== 'SELF_INITIATED'
                    && (researchSelectedEarlyPath($pdo, $researchId, 'REGULAR')
                        || (bool)$inhousePassedStmt->fetchColumn());
                $ethicsNextStatus = $ethicsAfterUpe
                    ? 'SELF_INITIATED_UPE_SUBMITTED'
                    : ($ethicsAfterInhouse ? 'READY_FOR_NTP' : 'ETHICS_COMPLETED');
                $ethicsNextStage = $ethicsAfterUpe
                    ? 'COMPLETED_RESEARCH'
                    : ($ethicsAfterInhouse ? 'NOTICE_TO_PROCEED' : 'ETHICS');
                $ethicsTrack = $ethicsAfterUpe ? 'SELF_INITIATED' : 'REGULAR';

                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            ethics_result = ?,
                            ethics_remarks = ?,
                            ethics_reviewed_by = ?,
                            ethics_reviewed_at = NOW(),
                            status = CASE WHEN ? = 1 THEN 'Approved' ELSE status END,
                            approved_at = CASE
                                WHEN ? = 1 THEN COALESCE(approved_at, NOW())
                                ELSE approved_at
                            END,

                            submission_track = ?,
                            workflow_status = ?,
                            workflow_stage = ?,
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND workflow_status = 'FOR_ETHICS_REVIEW'
                          AND workflow_stage = ?
                          AND is_archived = 0
                    ");

                $update->execute([
                    $ethicsResult,
                    $ethicsRemarks !== '' ? $ethicsRemarks : null,
                    (int)$user['user_id'],
                    $ethicsAfterInhouse ? 1 : 0,
                    $ethicsAfterInhouse ? 1 : 0,
                    $ethicsTrack,
                    $ethicsNextStatus,
                    $ethicsNextStage,
                    $researchId,
                    $ethicsEntryStage
                ]);


                if (
                    $update->rowCount() !== 1
                ) {

                    throw new RuntimeException(
                        'Ethics workflow record was not updated.'
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
                    'UREC/IACUC result recorded: '
                    . $resultLabel
                    . '. System-generated certificate is now available.'
                    . ($ethicsRemarks !== ''
                        ? ' Remarks: ' . $ethicsRemarks
                        : '')
                ]);


                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    'UREC/IACUC review completed',
                    'The UREC/IACUC review for your research "'
                    . $research['research_title']
                    . '" has been completed with classification: '
                    . $resultLabel
                    . '. Your system-generated UREC/IACUC certificate is now available.',
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );


                $pdo->commit();


                setFlash(
                    'success',
                    'UREC/IACUC result saved successfully: '
                    . $resultLabel
                    . '. The system-generated certificate is now available.'
                );


                redirect(
                    BASE_URL
                    . '/research/view.php?id='
                    . $researchId
                );


            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Save UREC/IACUC Result Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to save the UREC/IACUC result. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RPS SAVE URRC RESULT
    |--------------------------------------------------------------------------
    |
    | This is the missing URRC step after the faculty researcher clicks
    | For URRC. RPS records the URRC decision here.
    |
    */

    elseif (
        $action === 'save_urrc_result'
        &&
        !$errors
    ) {

        if (
            ($user['role'] ?? '') !== 'rps'
        ) {

            $errors[] =
                'Only Research Processing Staff may record the URRC result.';
        }


        $currentWorkflowStatus =
            (string)(
                $research['workflow_status']
                ?? ''
            );


        $allowedUrrcWorkflowStatuses = [
            'FOR_URRC',
            'URRC_REVISED_SUBMITTED'
        ];
        if (researchUrrcAwaitingRpsIntake($pdo, $researchId, $research)) {
            $errors[] = 'Complete initial RPS processing before recording a URRC decision.';
        }
        if ($currentWorkflowStatus === 'URRC_REVISED_SUBMITTED'
            && $rpsUrrcSubmittedRevisedDocument === null) {
            $errors[] = 'The submitted URRC revised manuscript link could not be verified. Please check the faculty submission record.';
        }


        if (
            !in_array(
                $currentWorkflowStatus,
                $allowedUrrcWorkflowStatuses,
                true
            )
        ) {

            $errors[] =
                'This research is not currently waiting for a URRC result.';
        }


        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {

            $errors[] =
                'Archived research cannot be processed.';
        }


        $urrcResult =
            strtoupper(
                trim(
                    (string)(
                        $_POST['urrc_result']
                        ?? ''
                    )
                )
            );


        $urrcRemarks =
            trim(
                (string)(
                    $_POST['urrc_remarks']
                    ?? ''
                )
            );


        $allowedUrrcResults = [
            'ACCEPTED',
            'ACCEPTED_WITH_REVISION',
            'REJECTED'
        ];


        if (
            !in_array(
                $urrcResult,
                $allowedUrrcResults,
                true
            )
        ) {

            $errors[] =
                'Please select a valid URRC result.';
        }

        // A submitted revision has already completed its Faculty turn.
        // The RPS verification action may only proceed to In-House.
        if ($currentWorkflowStatus === 'URRC_REVISED_SUBMITTED'
            && $urrcResult !== 'ACCEPTED') {
            $errors[] = 'This URRC revision has already been submitted. Verify it and proceed to In-House Review; do not request another revision.';
        }


        if (
            in_array(
                $urrcResult,
                [
                    'ACCEPTED_WITH_REVISION',
                    'REJECTED'
                ],
                true
            )
            &&
            $urrcRemarks === ''
        ) {

            $errors[] =
                'Remarks are required for URRC revision or rejection.';
        }


        if (
            mb_strlen($urrcRemarks) > 5000
        ) {

            $errors[] =
                'URRC remarks must not exceed 5,000 characters.';
        }


        if (!$errors) {

            try {

                $pdo->beginTransaction();


                $resultLabel =
                    match ($urrcResult) {
                        'ACCEPTED' => 'Accepted',
                        'ACCEPTED_WITH_REVISION' => 'Accepted with Revision',
                        'REJECTED' => 'Rejected',
                        default => $urrcResult
                    };


                $oldStatus =
                    (string)$research['status'];


                $newStatus = $oldStatus;
                $nextWorkflowStatus = '';
                $nextWorkflowStage = '';
                $archiveFlag = 0;
                $archivedReason = null;
                $historyRemarks = '';
                $notificationTitle = '';
                $notificationMessage = '';
                $successMessage = '';


                if ($urrcResult === 'ACCEPTED') {

                    $newStatus = 'Under Review';
                    $nextWorkflowStatus = 'FOR_INHOUSE';
                    $nextWorkflowStage = 'INHOUSE';
                    $archiveFlag = 0;
                    $archivedReason = null;

                    $historyRemarks =
                        'URRC result recorded: Accepted. Research moved to In-House review.'
                        . ($urrcRemarks !== '' ? ' Remarks: ' . $urrcRemarks : '');

                    $notificationTitle =
                        'URRC review accepted';

                    $notificationMessage =
                        'Your research "'
                        . $research['research_title']
                        . '" was accepted in URRC review and is now for In-House review.';

                    $successMessage =
                        'URRC result saved. Research moved to In-House Review.';

                } elseif ($urrcResult === 'ACCEPTED_WITH_REVISION') {

                    $newStatus = 'For Revision';
                    $nextWorkflowStatus = 'URRC_REVISION_REQUIRED';
                    $nextWorkflowStage = 'URRC';
                    $archiveFlag = 0;
                    $archivedReason = null;

                    $historyRemarks =
                        'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript.'
                        . ($urrcRemarks !== '' ? ' Remarks: ' . $urrcRemarks : '');

                    $notificationTitle =
                        'URRC revision required';

                    $notificationMessage =
                        'Your research "'
                        . $research['research_title']
                        . '" was accepted with revision in URRC review. Please upload the revised manuscript.';

                    $successMessage =
                        'URRC result saved. Researcher is required to submit a revised manuscript.';

                } else {

                    $newStatus = 'Archived';
                    $nextWorkflowStatus = 'URRC_REJECTED';
                    $nextWorkflowStage = 'ARCHIVED';
                    $archiveFlag = 1;
                    $archivedReason = 'Research rejected during URRC review.';

                    $historyRemarks =
                        'URRC result recorded: Rejected. Research archived.'
                        . ($urrcRemarks !== '' ? ' Remarks: ' . $urrcRemarks : '');

                    $notificationTitle =
                        'Research rejected in URRC review';

                    $notificationMessage =
                        'Your research "'
                        . $research['research_title']
                        . '" was rejected in URRC review and has been archived.';

                    $successMessage =
                        'URRC result saved. Research has been archived.';
                }


                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = ?,
                            workflow_status = ?,
                            workflow_stage = ?,
                            is_archived = ?,
                            archived_reason = ?,
                            archived_at =
                                CASE
                                    WHEN ? = 1 THEN NOW()
                                    ELSE NULL
                                END,
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND workflow_status IN
                          (
                              'FOR_URRC',
                              'URRC_REVISED_SUBMITTED'
                          )
                          AND is_archived = 0
                    ");


                $update->execute([
                    $newStatus,
                    $nextWorkflowStatus,
                    $nextWorkflowStage,
                    $archiveFlag,
                    $archivedReason,
                    $archiveFlag,
                    $researchId
                ]);


                if ($update->rowCount() !== 1) {

                    throw new RuntimeException(
                        'URRC workflow record was not updated.'
                    );
                }


                $evaluation =
                    $pdo->prepare("
                        INSERT INTO research_evaluations
                        (
                            research_id,
                            evaluator_id,
                            decision,
                            remarks
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ");


                $evaluation->execute([
                    $researchId,
                    (int)$user['user_id'],
                    'URRC ' . $resultLabel,
                    $urrcRemarks !== ''
                        ? $urrcRemarks
                        : null
                ]);


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
                    $oldStatus,
                    $newStatus,
                    $historyRemarks
                ]);


                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    $notificationTitle,
                    $notificationMessage
                    . ($urrcRemarks !== '' ? ' Remarks: ' . $urrcRemarks : ''),
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );


                $pdo->commit();


                setFlash(
                    'success',
                    $successMessage
                );


                redirect(
                    BASE_URL
                    . '/research/view.php?id='
                    . $researchId
                );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Save URRC Result Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to save the URRC result. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RPS ARCHIVE URRC - DID NOT REVISE
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'archive_urrc_no_revision'
        &&
        !$errors
    ) {

        if (
            ($user['role'] ?? '') !== 'rps'
        ) {

            $errors[] =
                'Only Research Processing Staff may archive this URRC revision record.';
        }


        if (
            ($research['workflow_status'] ?? '')
            !== 'URRC_REVISION_REQUIRED'
        ) {

            $errors[] =
                'This research is not currently waiting for a URRC revised manuscript.';
        }


        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {

            $errors[] =
                'This research is already archived.';
        }


        // A human reviewer may not close the 21-day revision period early.
        $deadlineState = remsUrrcRevisionDeadline($pdo, $researchId);
        if ($isEarlyUrrcRevision && (!$deadlineState || !(int)$deadlineState['expired'])) {
            $errors[] = 'The ' . ($deadlineState['period_label'] ?? '21-Day Deadline') . ' has not expired. Automatic archiving happens after the deadline.';
        }

        $noRevisionRemarks =
            trim(
                (string)(
                    $_POST['no_revision_remarks']
                    ?? ''
                )
            );


        if (
            mb_strlen($noRevisionRemarks) > 5000
        ) {

            $errors[] =
                'Did Not Revise remarks must not exceed 5,000 characters.';
        }


        if (!$errors) {

            try {

                $pdo->beginTransaction();


                $oldStatus =
                    (string)$research['status'];


                $archiveReason =
                    'Researcher did not submit the required URRC revised manuscript.';


                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = 'Archived',
                            workflow_status = 'URRC_DID_NOT_REVISE',
                            workflow_stage = 'ARCHIVED',
                            is_archived = 1,
                            archived_reason = ?,
                            archived_at = NOW(),
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND workflow_status = 'URRC_REVISION_REQUIRED'
                          AND is_archived = 0
                    ");


                $update->execute([
                    $archiveReason,
                    $researchId
                ]);


                if ($update->rowCount() !== 1) {

                    throw new RuntimeException(
                        'URRC no-revision archive record was not updated.'
                    );
                }


                $evaluation =
                    $pdo->prepare("
                        INSERT INTO research_evaluations
                        (
                            research_id,
                            evaluator_id,
                            decision,
                            remarks
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ");


                $evaluation->execute([
                    $researchId,
                    (int)$user['user_id'],
                    'URRC Did Not Revise',
                    $noRevisionRemarks !== ''
                        ? $noRevisionRemarks
                        : $archiveReason
                ]);


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
                            'Archived',
                            ?
                        )
                    ");


                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $oldStatus,
                    'URRC marked as Did Not Revise. Research archived.'
                    . ($noRevisionRemarks !== '' ? ' Remarks: ' . $noRevisionRemarks : '')
                ]);


                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    'Research archived after URRC revision period',
                    'Your research "'
                    . $research['research_title']
                    . '" has been archived because the required URRC revised manuscript was not submitted.'
                    . ($noRevisionRemarks !== '' ? ' Remarks: ' . $noRevisionRemarks : ''),
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );


                $pdo->commit();


                setFlash(
                    'success',
                    'Research archived successfully: Did Not Revise.'
                );


                redirect(
                    BASE_URL
                    . '/research/view.php?id='
                    . $researchId
                );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Archive URRC Did Not Revise Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to archive the research as Did Not Revise. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RPS SAVE IN-HOUSE RESULT
    |--------------------------------------------------------------------------
    |
    | This step happens after URRC Accepted. RPS records the In-House
    | decision before the research can move to Notice to Proceed.
    |
    */

    elseif (
        $action === 'save_inhouse_result'
        &&
        !$errors
    ) {

        if (
            ($user['role'] ?? '') !== 'rps'
        ) {

            $errors[] =
                'Only Research Processing Staff may record the In-House result.';
        }


        $currentWorkflowStatus =
            (string)(
                $research['workflow_status']
                ?? ''
            );


        $allowedInhouseWorkflowStatuses = [
            'FOR_INHOUSE',
            'INHOUSE_REVISED_SUBMITTED'
        ];


        if (
            !in_array(
                $currentWorkflowStatus,
                $allowedInhouseWorkflowStatuses,
                true
            )
        ) {

            $errors[] =
                'This research is not currently waiting for an In-House result.';
        }


        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {

            $errors[] =
                'Archived research cannot be processed.';
        }

        // Match the URRC revised-review action: no extra eye-button gate.
        // A valid document saved for this exact In-House revision is still required.
        if ($currentWorkflowStatus === 'INHOUSE_REVISED_SUBMITTED'
            && $latestInhouseRevisedDocument === null) {
            $errors[] = 'A new revised In-House manuscript link is required before proceeding.';
        }

        $inhouseResult =
            strtoupper(
                trim(
                    (string)(
                        $_POST['inhouse_result']
                        ?? ''
                    )
                )
            );


        $inhouseRemarks =
            trim(
                (string)(
                    $_POST['inhouse_remarks']
                    ?? ''
                )
            );


        $allowedInhouseResults = [
            'PASSED',
            'PASSED_WITH_REVISION',
            'FAILED'
        ];


        if (
            !in_array(
                $inhouseResult,
                $allowedInhouseResults,
                true
            )
        ) {

            $errors[] =
                'Please select a valid In-House result.';
        }

        // Verify the submitted In-House revision once, then continue forward.
        // A repeated revision outcome cannot reopen this finished round.
        if ($currentWorkflowStatus === 'INHOUSE_REVISED_SUBMITTED'
            && $inhouseResult !== 'PASSED') {
            $errors[] = 'This In-House revision has already been submitted. Verify it and proceed to UREC/IACUC Review; do not request another revision.';
        }


        if (
            in_array(
                $inhouseResult,
                [
                    'PASSED_WITH_REVISION',
                    'FAILED'
                ],
                true
            )
            &&
            $inhouseRemarks === ''
        ) {

            $errors[] =
                'Remarks are required for In-House revision or failed result.';
        }


        if (
            mb_strlen($inhouseRemarks) > 5000
        ) {

            $errors[] =
                'In-House remarks must not exceed 5,000 characters.';
        }


        if (!$errors) {

            try {

                $pdo->beginTransaction();


                $resultLabel =
                    match ($inhouseResult) {
                        'PASSED' => 'Passed',
                        'PASSED_WITH_REVISION' => 'Passed with Revision',
                        'FAILED' => 'Failed',
                        default => $inhouseResult
                    };


                $oldStatus =
                    (string)$research['status'];


                $newStatus = $oldStatus;
                $nextWorkflowStatus = '';
                $nextWorkflowStage = '';
                $archiveFlag = 0;
                $archivedReason = null;
                $historyRemarks = '';
                $notificationTitle = '';
                $notificationMessage = '';
                $successMessage = '';


                if ($inhouseResult === 'PASSED') {

                    // Every Regular/For-URRC manuscript must complete UREC/IACUC
                    // after passing In-House; the revision does not skip this step.
                    $newStatus = 'Under Review';
                    $nextWorkflowStatus = 'FOR_ETHICS_REVIEW';
                    $nextWorkflowStage = 'ETHICS';
                    $archiveFlag = 0;
                    $archivedReason = null;
                    $historyRemarks = 'In-House result recorded: Passed. Research moved to UREC/IACUC review.'
                        . ($inhouseRemarks !== '' ? ' Remarks: ' . $inhouseRemarks : '');
                    $notificationTitle = 'In-House review passed';
                    $notificationMessage = 'Your research "' . $research['research_title']
                        . '" passed In-House Review and is now for UREC/IACUC processing.';
                    $successMessage = 'In-House result saved. Research moved to UREC/IACUC review.';

                } elseif ($inhouseResult === 'PASSED_WITH_REVISION') {

                    $newStatus = 'For Revision';
                    $nextWorkflowStatus = 'INHOUSE_REVISION_REQUIRED';
                    $nextWorkflowStage = 'INHOUSE';
                    $archiveFlag = 0;
                    $archivedReason = null;

                    $historyRemarks =
                        'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript.'
                        . ($inhouseRemarks !== '' ? ' Remarks: ' . $inhouseRemarks : '');

                    $notificationTitle =
                        'In-House revision required';

                    $notificationMessage =
                        'Your research "'
                        . $research['research_title']
                        . '" passed with revision in In-House review. Please upload the revised manuscript.';

                    $successMessage =
                        'In-House result saved. Researcher is required to submit a revised manuscript.';

                } else {

                    $newStatus = 'Archived';
                    $nextWorkflowStatus = 'INHOUSE_FAILED';
                    $nextWorkflowStage = 'ARCHIVED';
                    $archiveFlag = 1;
                    $archivedReason = 'Research failed during In-House review.';

                    $historyRemarks =
                        'In-House result recorded: Failed. Research archived.'
                        . ($inhouseRemarks !== '' ? ' Remarks: ' . $inhouseRemarks : '');

                    $notificationTitle =
                        'Research failed In-House review';

                    $notificationMessage =
                        'Your research "'
                        . $research['research_title']
                        . '" failed the In-House review and has been archived.';

                    $successMessage =
                        'In-House result saved. Research has been archived.';
                }


                if ($inhouseResult === 'PASSED') {

                    $update =
                        $pdo->prepare("
                            UPDATE research_proposals

                            SET
                                status = ?,
                                workflow_status = ?,
                                workflow_stage = ?,
                                is_archived = ?,
                                archived_reason = ?,
                                archived_at = NULL,
                                approved_at = approved_at,
                                workflow_updated_at = NOW()

                            WHERE research_id = ?
                              AND workflow_status IN
                              (
                                  'FOR_INHOUSE',
                                  'INHOUSE_REVISED_SUBMITTED'
                              )
                              AND is_archived = 0
                        ");

                    $update->execute([
                        $newStatus,
                        $nextWorkflowStatus,
                        $nextWorkflowStage,
                        $archiveFlag,
                        $archivedReason,
                        $researchId
                    ]);

                } else {

                    $update =
                        $pdo->prepare("
                            UPDATE research_proposals

                            SET
                                status = ?,
                                workflow_status = ?,
                                workflow_stage = ?,
                                is_archived = ?,
                                archived_reason = ?,
                                archived_at =
                                    CASE
                                        WHEN ? = 1 THEN NOW()
                                        ELSE NULL
                                    END,
                                workflow_updated_at = NOW()

                            WHERE research_id = ?
                              AND workflow_status IN
                              (
                                  'FOR_INHOUSE',
                                  'INHOUSE_REVISED_SUBMITTED'
                              )
                              AND is_archived = 0
                        ");

                    $update->execute([
                        $newStatus,
                        $nextWorkflowStatus,
                        $nextWorkflowStage,
                        $archiveFlag,
                        $archivedReason,
                        $archiveFlag,
                        $researchId
                    ]);
                }


                if ($update->rowCount() !== 1) {

                    throw new RuntimeException(
                        'In-House workflow record was not updated.'
                    );
                }


                $evaluation =
                    $pdo->prepare("
                        INSERT INTO research_evaluations
                        (
                            research_id,
                            evaluator_id,
                            decision,
                            remarks
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ");


                $evaluation->execute([
                    $researchId,
                    (int)$user['user_id'],
                    'In-House ' . $resultLabel,
                    $inhouseRemarks !== ''
                        ? $inhouseRemarks
                        : null
                ]);


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
                    $oldStatus,
                    $newStatus,
                    $historyRemarks
                ]);


                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    $notificationTitle,
                    $notificationMessage
                    . ($inhouseRemarks !== '' ? ' Remarks: ' . $inhouseRemarks : ''),
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );


                $pdo->commit();


                setFlash(
                    'success',
                    $successMessage
                );


                redirect(
                    BASE_URL
                    . '/research/view.php?id='
                    . $researchId
                );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Save In-House Result Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to save the In-House result. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RPS ISSUE NOTICE TO PROCEED
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'issue_notice_to_proceed'
        &&
        !$errors
    ) {

        if (
            ($user['role'] ?? '') !== 'rps'
        ) {

            $errors[] =
                'Only Research Processing Staff may issue the Notice to Proceed.';
        }


        if (
            ($research['workflow_status'] ?? '') !== 'READY_FOR_NTP'
        ) {

            $errors[] =
                'This research is not currently ready for Notice to Proceed.';
        }


        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {

            $errors[] =
                'Archived research cannot be processed.';
        }


        $ntpRemarks =
            trim(
                (string)(
                    $_POST['ntp_remarks']
                    ?? ''
                )
            );


        if (
            mb_strlen($ntpRemarks) > 5000
        ) {

            $errors[] =
                'Notice to Proceed remarks must not exceed 5,000 characters.';
        }


        if (!$errors) {

            try {

                $pdo->beginTransaction();


                $oldStatus =
                    (string)$research['status'];


                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = 'Ongoing',
                            workflow_status = 'NTP_ISSUED',
                            workflow_stage = 'IMPLEMENTATION',
                            workflow_updated_at = NOW(),
                            approved_at = COALESCE(approved_at, NOW())

                        WHERE research_id = ?
                          AND workflow_status = 'READY_FOR_NTP'
                          AND is_archived = 0
                    ");


                $update->execute([
                    $researchId
                ]);


                if ($update->rowCount() !== 1) {

                    throw new RuntimeException(
                        'Notice to Proceed workflow record was not updated.'
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
                            'Ongoing',
                            ?
                        )
                    ");


                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $oldStatus,
                    'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation.'
                    . ($ntpRemarks !== '' ? ' Remarks: ' . $ntpRemarks : '')
                ]);


                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    'Notice to Proceed generated',
                    'Notice to Proceed has been generated for your research "'
                    . $research['research_title']
                    . '". You may now proceed with implementation.'
                    . ($ntpRemarks !== '' ? ' Remarks: ' . $ntpRemarks : ''),
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );


                $pdo->commit();


                setFlash(
                    'success',
                    'Notice to Proceed generated successfully. Please review the generated document.'
                );


                redirect(
                    BASE_URL
                    . '/research/view.php?id='
                    . $researchId
                    . '&certificate=notice_to_proceed'
                );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Issue Notice to Proceed Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to issue Notice to Proceed. Please try again.';
            }
        }
    }




    /*
    |--------------------------------------------------------------------------
    | RPS CONFIRM COMPLETED MANUSCRIPT IN-HOUSE CHECK
    |--------------------------------------------------------------------------
    |
    | This OK step happens after the faculty researcher uploads the
    | manuscript of completed research during Project Monitoring.
    |
    */

    elseif (
        $action === 'confirm_completed_manuscript_inhouse'
        &&
        !$errors
    ) {

        if (
            ($user['role'] ?? '') !== 'rps'
        ) {

            $errors[] =
                'Only Research Processing Staff may confirm the completed manuscript.';
        }


        if (
            ($research['workflow_status'] ?? '') !== 'COMPLETED_MANUSCRIPT_SUBMITTED'
        ) {

            $errors[] =
                'This research is not currently waiting for completed manuscript confirmation.';
        }


        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {

            $errors[] =
                'Archived research cannot be processed.';
        }


        if (!$errors) {

            try {

                $pdo->beginTransaction();


                $oldStatus =
                    (string)$research['status'];


                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = 'Ongoing',
                            workflow_status = 'FOR_TERMINAL_REPORT',
                            workflow_stage = 'TERMINAL_REPORT',
                            progress_percentage = 100,
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND workflow_status = 'COMPLETED_MANUSCRIPT_SUBMITTED'
                          AND is_archived = 0
                    ");


                $update->execute([
                    $researchId
                ]);


                if ($update->rowCount() !== 1) {

                    throw new RuntimeException(
                        'Completed manuscript workflow record was not updated.'
                    );
                }


                $evaluation =
                    $pdo->prepare("
                        INSERT INTO research_evaluations
                        (
                            research_id,
                            evaluator_id,
                            decision,
                            remarks
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            'Completed Manuscript In-House OK',
                            'Completed research manuscript confirmed by Research Processing Staff.'
                        )
                    ");


                $evaluation->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);


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
                            'Ongoing',
                            'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.'
                        )
                    ");


                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $oldStatus
                ]);


                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    'Completed manuscript confirmed',
                    'Your completed research manuscript for "'
                    . $research['research_title']
                    . '" has been confirmed by the Research Office. You may proceed to the next requirement.',
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );


                $pdo->commit();


                setFlash(
                    'success',
                    'Completed manuscript confirmed successfully.'
                );


                redirect(
                    BASE_URL
                    . '/research/view.php?id='
                    . $researchId
                );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Confirm Completed Manuscript In-House Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to confirm the completed manuscript. Please try again.';
            }
        }
    }






/*
|--------------------------------------------------------------------------
| RPS CONFIRM TERMINAL REPORT — OK REQUIRED BEFORE PERMITS
|--------------------------------------------------------------------------
| The researcher has submitted the Terminal Report, but RPS must press OK
| before the Publication / Presentation permit form is made available.
| This records an auditable confirmation without changing workflow status.
*/

elseif (
    $action === 'confirm_terminal_report_rps'
    && !$errors
) {
    if (($user['role'] ?? '') !== 'rps') {
        $errors[] = 'Only Research Processing Staff may confirm the Terminal Report.';
    }

    if (($research['workflow_status'] ?? '') !== 'TERMINAL_REPORT_SUBMITTED') {
        $errors[] = 'The Terminal Report is not waiting for RPS confirmation.';
    }

    if ((int)($research['is_archived'] ?? 0) === 1) {
        $errors[] = 'Archived research cannot be processed.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock this proposal to avoid two concurrent OK clicks recording
            // duplicate confirmations (and re-check its current status).
            $lockStmt = $pdo->prepare("
                SELECT workflow_status, is_archived
                FROM research_proposals
                WHERE research_id = ?
                FOR UPDATE
            ");
            $lockStmt->execute([$researchId]);
            $lockedResearch = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedResearch
                || $lockedResearch['workflow_status'] !== 'TERMINAL_REPORT_SUBMITTED'
                || (int)$lockedResearch['is_archived'] === 1) {
                throw new RuntimeException('Terminal Report is no longer awaiting confirmation.');
            }

            $documentCheck = $pdo->prepare("
                SELECT 1
                FROM research_documents
                WHERE research_id = ?
                  AND LOWER(TRIM(document_type)) IN
                      ('terminal report', 'research terminal report', 'final terminal report')
                LIMIT 1
            ");
            $documentCheck->execute([$researchId]);
            if (!$documentCheck->fetchColumn()) {
                throw new RuntimeException('No submitted Terminal Report was found.');
            }

            if (!researchTerminalReportConfirmed($pdo, $researchId)) {
                $evaluation = $pdo->prepare("
                    INSERT INTO research_evaluations
                    (research_id, evaluator_id, decision, remarks)
                    VALUES (?, ?, 'Terminal Report RPS OK',
                        'Submitted Terminal Report checked and confirmed by Research Processing Staff.')
                ");
                $evaluation->execute([$researchId, (int)$user['user_id']]);

                $history = $pdo->prepare("
                    INSERT INTO research_status_history
                    (research_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    (string)$research['status'],
                    'Terminal Report checked and confirmed by Research Processing Staff. '
                    . '[TERMINAL_REPORT_RPS_CONFIRMED]'
                ]);
            }

            $pdo->commit();
            redirect(BASE_URL . '/research/view.php?id=' . $researchId);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Confirm Terminal Report RPS Error: ' . $e->getMessage());
            $errors[] = 'Unable to confirm the Terminal Report. Please verify the submitted report and try again.';
        }
    }
}

    /*
    |--------------------------------------------------------------------------
    | RPS ISSUE PERMIT FOR PUBLICATION / PRESENTATION
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'issue_publication_presentation_permit'
        && !$errors
    ) {
        if (($user['role'] ?? '') !== 'rps') {
            $errors[] = 'Only Research Processing Staff may issue publication and presentation permits.';
        }

        if (($research['workflow_status'] ?? '') !== 'TERMINAL_REPORT_SUBMITTED') {
            $errors[] = 'This research is not currently waiting for permit processing.';
        }

        if ((int)($research['is_archived'] ?? 0) === 1) {
            $errors[] = 'Archived research cannot be processed.';
        }

        // Server-side rule: the Permit form must never bypass the RPS OK step.
        if (!$errors && !researchTerminalReportConfirmed($pdo, $researchId)) {
            $errors[] = 'Confirm the submitted Terminal Report with OK before generating permits.';
        }

        $permitRemarks = trim((string)($_POST['permit_remarks'] ?? ''));
        if (mb_strlen($permitRemarks) > 5000) {
            $errors[] = 'Permit remarks must not exceed 5,000 characters.';
        }

        // Both permits require the same completed Terminal Report.
        if (!$errors) {
            $terminalReportCheck = $pdo->prepare("
                SELECT COUNT(*)
                FROM research_documents
                WHERE research_id = ?
                  AND LOWER(TRIM(document_type)) IN
                  (
                      'terminal report',
                      'research terminal report',
                      'final terminal report'
                  )
            ");
            $terminalReportCheck->execute([$researchId]);
            if ((int)$terminalReportCheck->fetchColumn() < 1) {
                $errors[] = 'The Terminal Report record could not be verified.';
            }
        }

        if (!$errors) {
            try {
                // Exactly one workflow transition and one permanent audit marker.
                // Both certificates are derived from this same issued pair.
                $pdo->beginTransaction();
                $oldStatus = (string)$research['status'];

                $update = $pdo->prepare("
                    UPDATE research_proposals
                    SET
                        status = 'Ongoing',
                        workflow_status = 'PERMIT_ISSUED',
                        workflow_stage = 'PUBLICATION_PRESENTATION',
                        progress_percentage = 95,
                        workflow_updated_at = NOW()
                    WHERE research_id = ?
                      AND status = 'Ongoing'
                      AND workflow_status = 'TERMINAL_REPORT_SUBMITTED'
                      AND is_archived = 0
                      AND EXISTS (
                          SELECT 1
                          FROM research_status_history rsh
                          INNER JOIN users approver ON approver.user_id = rsh.changed_by
                          WHERE rsh.research_id = research_proposals.research_id
                            AND approver.role = 'rps'
                            AND rsh.remarks = 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]'
                      )
                ");
                $update->execute([$researchId]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Permit workflow record was not updated.');
                }

                // Issue the two permits, but keep the research ongoing until
                // the faculty researcher submits the final publication/presentation proof.
                $historyRemarks =
                    'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. '
                    . '[PERMIT_TYPE:BOTH]'
                    . ($permitRemarks !== '' ? ' Remarks: ' . $permitRemarks : '');

                $history = $pdo->prepare("
                    INSERT INTO research_status_history
                    (research_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, ?, 'Ongoing', ?)
                ");
                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $oldStatus,
                    $historyRemarks
                ]);
                $pdo->commit();

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Issue Both Publication/Presentation Permits Error: ' . $e->getMessage());
                $errors[] = 'Unable to generate both permits. Please try again.';
            }

            if (!$errors) {
                // Supplemental evaluations are helpful for audit reporting but
                // a logging issue must not roll back already-issued permits.
                try {
                    $evaluation = $pdo->prepare("
                        INSERT INTO research_evaluations
                        (research_id, evaluator_id, decision, remarks)
                        VALUES (?, ?, ?, ?)
                    ");
                    foreach (['Publication', 'Presentation'] as $permitLabel) {
                        $evaluation->execute([
                            $researchId,
                            (int)$user['user_id'],
                            'Permit Issued - ' . $permitLabel,
                            $permitRemarks !== ''
                                ? $permitRemarks
                                : 'Permit generated after review of the submitted Terminal Report.'
                        ]);
                    }
                } catch (Throwable $evaluationError) {
                    error_log('Both Permits Evaluation Log Warning: ' . $evaluationError->getMessage());
                }

                try {
                    createNotification(
                        $pdo,
                        (int)$research['researcher_id'],
                        'Publication and Presentation permits issued',
                        'Both the Permit for Publication and the Permit for Presentation have been issued for your research "'
                            . $research['research_title']
                            . '". Please submit proof of publication or presentation to complete the research record. Both permits remain available to view and print.',
                        'research',
                        $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId
                    );
                } catch (Throwable $notificationError) {
                    error_log('Both Permits Notification Warning: ' . $notificationError->getMessage());
                }

                setFlash('success', 'Both Publication and Presentation permits generated successfully.');
                redirect(BASE_URL . '/research/view.php?id=' . $researchId);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FACULTY SELECT SELF-INITIATED TRACK
    |--------------------------------------------------------------------------
    |
    | This is a separate action. The existing For URRC action remains
    | continue_after_ethics and is not changed by this block.
    |
    */

    elseif (
        $action === 'select_self_initiated'
        &&
        !$errors
    ) {

        if (($user['role'] ?? '') !== 'faculty') {
            $errors[] =
                'Only the faculty researcher may select the Self-Initiated track.';
        }

        if (
            (int)$research['researcher_id']
            !== (int)$user['user_id']
        ) {
            $errors[] =
                'You are not authorized to update this research.';
        }

        if (
            ($research['workflow_status'] ?? '')
            !== 'ETHICS_COMPLETED'
        ) {
            $errors[] =
                'Self-Initiated may be selected only after UREC/IACUC review is completed.';
        }

        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {
            $errors[] =
                'Archived research cannot be processed.';
        }

        if (!$errors) {

            try {

                $pdo->beginTransaction();

                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            submission_track = 'SELF_INITIATED',
                            workflow_status = 'SELF_INITIATED_UPE_REQUIRED',
                            workflow_stage = 'UPE',
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND workflow_status = 'ETHICS_COMPLETED'
                          AND is_archived = 0
                    ");

                $update->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);

                if ($update->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Self-Initiated workflow record was not updated.'
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
                        VALUES (?, ?, ?, ?, ?)
                    ");

                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    (string)$research['status'],
                    'Faculty researcher selected the Self-Initiated track. Two UPE certificates are now required.'
                ]);

                $pdo->commit();

                try {
                    notifyUsersByRole(
                        $pdo,
                        ['rps', 'admin'],
                        'Self-Initiated research selected',
                        fullName($user)
                        . ' selected the Self-Initiated track for research '
                        . ($research['manuscript_no'] ?: '#' . $researchId)
                        . '. Two UPE certificates are now required.',
                        'research',
                        $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $notificationError) {
                    error_log(
                        'Self-Initiated Notification Warning: '
                        . $notificationError->getMessage()
                    );
                }

                setFlash(
                    'success',
                    'Self-Initiated track selected. Please upload the two required UPE certificates.'
                );

                redirect(
                    BASE_URL . '/research/view.php?id=' . $researchId
                );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Select Self-Initiated Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to select the Self-Initiated track. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FACULTY UPLOAD SELF-INITIATED UPE CERTIFICATE
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'upload_upe_certificate'
        &&
        !$errors
    ) {

        if (($user['role'] ?? '') !== 'faculty') {
            $errors[] =
                'Only the faculty researcher may upload UPE certificates.';
        }

        if (
            (int)$research['researcher_id']
            !== (int)$user['user_id']
        ) {
            $errors[] =
                'You are not authorized to upload certificates for this research.';
        }

        if (
            ($research['workflow_status'] ?? '')
            !== 'SELF_INITIATED_UPE_REQUIRED'
        ) {
            $errors[] =
                'UPE certificate upload is not available at the current workflow step.';
        }

        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {
            $errors[] =
                'Archived research cannot be processed.';
        }

        $upeSlot =
            (int)($_POST['upe_slot'] ?? 0);

        if (!in_array($upeSlot, [1, 2], true)) {
            $errors[] =
                'Please select a valid UPE certificate slot.';
        }

        if (
            $upeSlot === 1
            &&
            $hasUpeCertificate1Document
        ) {
            $errors[] =
                'UPE Certificate 1 has already been uploaded.';
        }

        if (
            $upeSlot === 2
            &&
            $hasUpeCertificate2Document
        ) {
            $errors[] =
                'UPE Certificate 2 has already been uploaded.';
        }

        $upeCertificateFile = null;

        if (!$errors) {
            $upeCertificateFile =
                validateResearchUpeCertificateUpload(
                    'upe_certificate_file',
                    $upeCertificateAllowedExtensions,
                    $upeCertificateAllowedMimeTypes,
                    $upeCertificateMaxFileSize,
                    $errors
                );
        }

        if (
            !$errors
            &&
            $upeCertificateFile
        ) {

            $movedUpeCertificatePath = null;

            try {

                // MySQL ALTER TABLE may cause an implicit commit, so keep it
                // outside the upload transaction.
                ensureResearchDocumentTypesAreFlexible($pdo);

                $pdo->beginTransaction();

                $upeUpload =
                    moveResearchUpeCertificateUpload(
                        $upeCertificateFile,
                        $upeCertificateUploadDirectory,
                        $researchId,
                        $upeSlot
                    );

                $movedUpeCertificatePath =
                    $upeUpload['physical_path'];

                $upeDocumentType =
                    'UPE Certificate ' . $upeSlot;

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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                $documentInsert->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $upeDocumentType,
                    $upeCertificateFile['original_name'],
                    $upeUpload['stored_name'],
                    $upeUpload['database_path'],
                    $upeCertificateFile['extension'],
                    $upeCertificateFile['size']
                ]);

                $bothUpeCertificatesComplete =
                    (
                        $upeSlot === 1
                        ? true
                        : $hasUpeCertificate1Document
                    )
                    &&
                    (
                        $upeSlot === 2
                        ? true
                        : $hasUpeCertificate2Document
                    );

                if ($bothUpeCertificatesComplete) {

                    // New pathway: UPE first, then UREC/IACUC (wired in Step 3).
                    // Legacy post-ethics UPE records retain their existing transition.
                    $upePreEthics =
                        ($research['workflow_stage'] ?? '') === 'UPE_BEFORE_ETHICS';
                    $upeCompleteStatus = $upePreEthics
                        ? 'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS'
                        : 'SELF_INITIATED_UPE_SUBMITTED';
                    $upeCompleteStage = $upePreEthics ? 'ETHICS' : 'UPE';

                    $workflowUpdate =
                        $pdo->prepare("
                            UPDATE research_proposals

                            SET
                                submission_track = 'SELF_INITIATED',
                                workflow_status = ?,
                                workflow_stage = ?,
                                workflow_updated_at = NOW()

                            WHERE research_id = ?
                              AND researcher_id = ?
                              AND workflow_status = 'SELF_INITIATED_UPE_REQUIRED'
                              AND is_archived = 0
                        ");

                    $workflowUpdate->execute([
                        $upeCompleteStatus,
                        $upeCompleteStage,
                        $researchId,
                        (int)$user['user_id']
                    ]);

                    if ($workflowUpdate->rowCount() !== 1) {
                        throw new RuntimeException(
                            'UPE workflow record was not updated after both certificates were uploaded.'
                        );
                    }

                } else {

                    $workflowUpdate =
                        $pdo->prepare("
                            UPDATE research_proposals
                            SET
                                submission_track = 'SELF_INITIATED',
                                workflow_updated_at = NOW()
                            WHERE research_id = ?
                              AND researcher_id = ?
                              AND workflow_status = 'SELF_INITIATED_UPE_REQUIRED'
                              AND is_archived = 0
                        ");

                    $workflowUpdate->execute([
                        $researchId,
                        (int)$user['user_id']
                    ]);
                }

                $historyRemarks =
                    'UPE Certificate '
                    . $upeSlot
                    . ' uploaded by the faculty researcher.'
                    . (
                        $bothUpeCertificatesComplete
                            ? ' Both required UPE certificates are now complete.'
                            : ' One more UPE certificate is still required.'
                    );

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
                        VALUES (?, ?, ?, ?, ?)
                    ");

                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    (string)$research['status'],
                    $historyRemarks
                ]);

                $pdo->commit();

                try {
                    notifyUsersByRole(
                        $pdo,
                        ['rps', 'admin'],
                        $bothUpeCertificatesComplete
                            ? 'UPE certificates completed'
                            : 'UPE certificate uploaded',
                        fullName($user)
                        . ' uploaded '
                        . $upeDocumentType
                        . ' for research '
                        . ($research['manuscript_no'] ?: '#' . $researchId)
                        . (
                            $bothUpeCertificatesComplete
                                ? '. Both required UPE certificates are complete.'
                                : '.'
                        ),
                        'research',
                        $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $notificationError) {
                    error_log(
                        'UPE Certificate Notification Warning: '
                        . $notificationError->getMessage()
                    );
                }

                setFlash(
                    'success',
                    $bothUpeCertificatesComplete
                        ? 'Both UPE certificates were uploaded successfully.'
                        : 'UPE Certificate ' . $upeSlot . ' uploaded successfully.'
                );

                redirect(
                    BASE_URL . '/research/view.php?id=' . $researchId
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
                    $movedUpeCertificatePath
                    &&
                    is_file($movedUpeCertificatePath)
                ) {
                    @unlink($movedUpeCertificatePath);
                }

                error_log(
                    'UPE Certificate Upload Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to upload the UPE certificate. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FACULTY UPLOAD SELF-INITIATED COMPLETED RESEARCH / WHOLE MANUSCRIPT
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'upload_self_initiated_completed_research'
        &&
        !$errors
    ) {

        if (($user['role'] ?? '') !== 'faculty') {
            $errors[] =
                'Only the faculty researcher may upload the completed research file.';
        }

        if (
            (int)$research['researcher_id']
            !== (int)$user['user_id']
        ) {
            $errors[] =
                'You are not authorized to upload a completed research file for this research.';
        }

        if (
            ($research['workflow_status'] ?? '')
            !== 'SELF_INITIATED_UPE_SUBMITTED'
        ) {
            $errors[] =
                'Completed research upload is not available at the current workflow step.';
        }

        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {
            $errors[] =
                'Archived research cannot be processed.';
        }

        if ($hasSelfInitiatedCompletedResearchDocument) {
            $errors[] =
                'The completed research / whole manuscript has already been uploaded.';
        }

        $completedResearchFile = null;

        if (!$errors) {
            $completedResearchFile =
                validateResearchSelfInitiatedCompletedResearchUpload(
                    'self_initiated_completed_research_file',
                    $selfInitiatedResearchAllowedExtensions,
                    $selfInitiatedResearchAllowedMimeTypes,
                    $selfInitiatedResearchMaxFileSize,
                    $errors
                );
        }

        if (
            !$errors
            &&
            $completedResearchFile
        ) {

            $movedCompletedResearchPath = null;

            try {
                ensureResearchDocumentTypesAreFlexible($pdo);

                $pdo->beginTransaction();

                $completedResearchUpload =
                    moveResearchSelfInitiatedCompletedResearchUpload(
                        $completedResearchFile,
                        $selfInitiatedResearchUploadDirectory,
                        $researchId
                    );

                $movedCompletedResearchPath =
                    $completedResearchUpload['physical_path'];

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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                $documentInsert->execute([
                    $researchId,
                    (int)$user['user_id'],
                    'Self-Initiated Completed Research',
                    $completedResearchFile['original_name'],
                    $completedResearchUpload['stored_name'],
                    $completedResearchUpload['database_path'],
                    $completedResearchFile['extension'],
                    $completedResearchFile['size']
                ]);

                $workflowUpdate =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            submission_track = 'SELF_INITIATED',
                            workflow_status = 'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED',
                            workflow_stage = 'COMPLETED_RESEARCH',
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND workflow_status = 'SELF_INITIATED_UPE_SUBMITTED'
                          AND is_archived = 0
                    ");

                $workflowUpdate->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);

                if ($workflowUpdate->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Self-initiated completed research workflow record was not updated.'
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
                        VALUES (?, ?, ?, ?, ?)
                    ");

                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    (string)$research['status'],
                    'Completed research / whole manuscript uploaded by the faculty researcher for the Self-Initiated workflow.'
                ]);

                $pdo->commit();

                try {
                    notifyUsersByRole(
                        $pdo,
                        ['rps', 'admin'],
                        'Self-initiated completed research uploaded',
                        fullName($user)
                        . ' uploaded the completed research / whole manuscript for '
                        . ($research['manuscript_no'] ?: '#' . $researchId)
                        . ': '
                        . $research['research_title']
                        . '.',
                        'research',
                        $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $notificationError) {
                    error_log(
                        'Self-Initiated Completed Research Notification Warning: '
                        . $notificationError->getMessage()
                    );
                }

                setFlash(
                    'success',
                    'Completed research / whole manuscript uploaded successfully.'
                );

                redirect(
                    BASE_URL . '/research/view.php?id=' . $researchId
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
                    $movedCompletedResearchPath
                    &&
                    is_file($movedCompletedResearchPath)
                ) {
                    @unlink($movedCompletedResearchPath);
                }

                error_log(
                    'Self-Initiated Completed Research Upload Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to upload the completed research / whole manuscript. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FACULTY UPLOAD SELF-INITIATED TERMINAL REPORT
    |--------------------------------------------------------------------------
    |
    | Researcher-side only. This does not add or change any RPS action.
    | The next Self-Initiated step is system-generated permit processing.
    |
    */

    elseif (
        $action === 'upload_self_initiated_terminal_report'
        &&
        !$errors
    ) {

        if (($user['role'] ?? '') !== 'faculty') {
            $errors[] =
                'Only the faculty researcher may upload the Terminal Report.';
        }

        if (
            (int)$research['researcher_id']
            !== (int)$user['user_id']
        ) {
            $errors[] =
                'You are not authorized to upload a Terminal Report for this research.';
        }

        if (
            ($research['workflow_status'] ?? '')
            !== 'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED'
        ) {
            $errors[] =
                'Terminal Report upload is not available at the current Self-Initiated workflow step.';
        }

        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {
            $errors[] =
                'Archived research cannot be processed.';
        }

        if (!$hasSelfInitiatedCompletedResearchDocument) {
            $errors[] =
                'The completed research / whole manuscript must be uploaded first.';
        }

        if ($hasSelfInitiatedTerminalReportDocument) {
            $errors[] =
                'The Terminal Report has already been uploaded.';
        }

        $terminalRemarks =
            trim(
                (string)(
                    $_POST['self_initiated_terminal_remarks']
                    ?? ''
                )
            );

        if (mb_strlen($terminalRemarks) > 5000) {
            $errors[] =
                'Terminal Report remarks must not exceed 5,000 characters.';
        }

        $terminalReportFile = null;

        if (!$errors) {
            $terminalReportFile =
                validateResearchSelfInitiatedTerminalReportUpload(
                    'self_initiated_terminal_report_file',
                    $selfInitiatedTerminalReportAllowedExtensions,
                    $selfInitiatedTerminalReportAllowedMimeTypes,
                    $selfInitiatedTerminalReportMaxFileSize,
                    $errors
                );
        }

        if (
            !$errors
            &&
            $terminalReportFile
        ) {

            $movedTerminalReportPath = null;

            try {
                ensureResearchDocumentTypesAreFlexible($pdo);

                $pdo->beginTransaction();

                $terminalReportUpload =
                    moveResearchSelfInitiatedTerminalReportUpload(
                        $terminalReportFile,
                        $selfInitiatedTerminalReportUploadDirectory,
                        $researchId
                    );

                $movedTerminalReportPath =
                    $terminalReportUpload['physical_path'];

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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                $documentInsert->execute([
                    $researchId,
                    (int)$user['user_id'],
                    'Self-Initiated Terminal Report',
                    $terminalReportFile['original_name'],
                    $terminalReportUpload['stored_name'],
                    $terminalReportUpload['database_path'],
                    $terminalReportFile['extension'],
                    $terminalReportFile['size']
                ]);

                $workflowUpdate =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            submission_track = 'SELF_INITIATED',
                            workflow_status = 'SELF_INITIATED_PERMIT_GENERATED',
                            workflow_stage = 'PERMIT',
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND workflow_status = 'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED'
                          AND is_archived = 0
                    ");

                $workflowUpdate->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);

                if ($workflowUpdate->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Self-Initiated Terminal Report workflow record was not updated.'
                    );
                }

                $historyRemarks =
                    'Terminal Report uploaded by the faculty researcher for the Self-Initiated workflow.'
                    . (
                        $terminalRemarks !== ''
                            ? ' Remarks: ' . $terminalRemarks
                            : ''
                    )
                    . ' The system-generated Permit to Publish / Present is now available.';

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
                        VALUES (?, ?, ?, ?, ?)
                    ");

                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    (string)$research['status'],
                    $historyRemarks
                ]);

                $pdo->commit();

                setFlash(
                    'success',
                    'Terminal Report submitted successfully. Your Permit to Publish / Present has been generated automatically.'
                );

                redirect(
                    BASE_URL . '/research/view.php?id=' . $researchId
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
                    $movedTerminalReportPath
                    &&
                    is_file($movedTerminalReportPath)
                ) {
                    @unlink($movedTerminalReportPath);
                }

                error_log(
                    'Self-Initiated Terminal Report Upload Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to submit the Terminal Report. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FACULTY UPLOAD SELF-INITIATED PUBLICATION
    |--------------------------------------------------------------------------
    |
    | Researcher-side only. The Self-Initiated flow ends after publication upload.
    | No RPS action is added or changed by this block.
    |
    */

    elseif (
        $action === 'upload_self_initiated_publication_proof'
        &&
        !$errors
    ) {

        if (($user['role'] ?? '') !== 'faculty') {
            $errors[] =
                'Only the faculty researcher may upload the publication file.';
        }

        if (
            (int)$research['researcher_id']
            !== (int)$user['user_id']
        ) {
            $errors[] =
                'You are not authorized to upload a publication file for this research.';
        }

        if (
            !in_array(
                (string)($research['workflow_status'] ?? ''),
                [
                    'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED',
                    'SELF_INITIATED_PERMIT_GENERATED'
                ],
                true
            )
        ) {
            $errors[] =
                'Publication upload is not available at the current Self-Initiated workflow step.';
        }

        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {
            $errors[] =
                'Archived research cannot be processed.';
        }

        if (!$hasSelfInitiatedTerminalReportDocument) {
            $errors[] =
                'The Terminal Report must be uploaded before the publication file can be submitted.';
        }

        if ($hasSelfInitiatedPublicationProofDocument) {
            $errors[] =
                'The publication file has already been uploaded.';
        }

        $proofFile = null;

        if (!$errors) {
            $proofFile =
                validateResearchSelfInitiatedProofUpload(
                    'self_initiated_publication_proof_file',
                    $selfInitiatedProofAllowedExtensions,
                    $selfInitiatedProofAllowedMimeTypes,
                    $selfInitiatedProofMaxFileSize,
                    $errors
                );
        }

        if (
            !$errors
            &&
            $proofFile
        ) {

            $movedProofPath = null;

            try {
                ensureResearchDocumentTypesAreFlexible($pdo);

                $pdo->beginTransaction();

                $proofUpload =
                    moveResearchSelfInitiatedProofUpload(
                        $proofFile,
                        $selfInitiatedProofUploadDirectory,
                        $researchId
                    );

                $movedProofPath =
                    $proofUpload['physical_path'];

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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                $documentInsert->execute([
                    $researchId,
                    (int)$user['user_id'],
                    'Self-Initiated Publication',
                    $proofFile['original_name'],
                    $proofUpload['stored_name'],
                    $proofUpload['database_path'],
                    $proofFile['extension'],
                    $proofFile['size']
                ]);

                $oldStatus = (string)$research['status'];

                $workflowUpdate =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            status = 'Completed',
                            submission_track = 'SELF_INITIATED',
                            workflow_status = 'SELF_INITIATED_COMPLETED',
                            workflow_stage = 'COMPLETED',
                            progress_percentage = 100,
                            completed_at = COALESCE(completed_at, NOW()),
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND workflow_status IN
                          (
                              'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED',
                              'SELF_INITIATED_PERMIT_GENERATED'
                          )
                          AND is_archived = 0
                    ");

                $workflowUpdate->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);

                if ($workflowUpdate->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Self-Initiated completion workflow record was not updated.'
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
                        VALUES (?, ?, ?, 'Completed', ?)
                    ");

                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    $oldStatus,
                    'Publication file uploaded by the faculty researcher. Self-Initiated research workflow completed.'
                ]);

                $pdo->commit();

                setFlash(
                    'success',
                    'Publication uploaded successfully. The Self-Initiated research workflow is now complete.'
                );

                redirect(
                    BASE_URL . '/research/view.php?id=' . $researchId
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
                    $movedProofPath
                    &&
                    is_file($movedProofPath)
                ) {
                    @unlink($movedProofPath);
                }

                error_log(
                    'Self-Initiated Publication Upload Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to upload the publication file. Please try again.';
            }
        }
    }




    /*
    |--------------------------------------------------------------------------
    | FACULTY CONTINUE AFTER ETHICS REVIEW - NORMAL / FOR URRC PATH
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | - This button ALWAYS means the normal For URRC pathway.
    | - It never reads the old submission_track value.
    | - Self-Initiated / UPE can only be entered through select_self_initiated.
    | - RPS section comments do not affect this branch.
    |
    */

    elseif (
        $action === 'continue_after_ethics'
        &&
        !$errors
    ) {

        if (($user['role'] ?? '') !== 'faculty') {
            $errors[] =
                'Only the faculty researcher may continue this research to URRC.';
        }

        if (
            (int)$research['researcher_id']
            !== (int)$user['user_id']
        ) {
            $errors[] =
                'You are not authorized to continue this research.';
        }

        if (
            ($research['workflow_status'] ?? '')
            !== 'ETHICS_COMPLETED'
        ) {
            $errors[] =
                'The UREC/IACUC review must be completed before continuing to URRC.';
        }

        if (
            (int)($research['is_archived'] ?? 0) === 1
        ) {
            $errors[] =
                'Archived research cannot be forwarded to URRC.';
        }

        if (!$errors) {

            try {

                $pdo->beginTransaction();

                $update =
                    $pdo->prepare("
                        UPDATE research_proposals

                        SET
                            submission_track = 'REGULAR',
                            status = 'Under Review',
                            workflow_status = 'FOR_URRC',
                            workflow_stage = 'URRC',
                            workflow_updated_at = NOW()

                        WHERE research_id = ?
                          AND researcher_id = ?
                          AND workflow_status = 'ETHICS_COMPLETED'
                          AND is_archived = 0
                    ");

                $update->execute([
                    $researchId,
                    (int)$user['user_id']
                ]);

                if ($update->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Unable to forward the research to URRC.'
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
                        VALUES (?, ?, ?, 'Under Review', ?)
                    ");

                $history->execute([
                    $researchId,
                    (int)$user['user_id'],
                    (string)$research['status'],
                    'Faculty researcher selected the normal For URRC pathway after completion of the UREC/IACUC review. Research forwarded for URRC processing.'
                ]);

                $pdo->commit();

                try {
                    notifyUsersByRole(
                        $pdo,
                        ['rps'],
                        'Research forwarded for URRC processing',
                        'Research '
                        . ($research['manuscript_no'] ?: '#' . $researchId)
                        . ' has been forwarded by the faculty researcher for URRC processing.',
                        'research',
                        $researchId,
                        BASE_URL . '/research/view.php?id=' . $researchId,
                        (int)$user['user_id']
                    );
                } catch (Throwable $notificationError) {
                    error_log(
                        'For URRC Notification Warning: '
                        . $notificationError->getMessage()
                    );
                }

                setFlash(
                    'success',
                    'Research forwarded to URRC successfully.'
                );

                redirect(
                    BASE_URL
                    . '/research/view.php?id='
                    . $researchId
                );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'For URRC Error: '
                    . $e->getMessage()
                );

                $errors[] =
                    'Unable to forward the research to URRC. Please try again.';
            }
        }
    }



    /*
    |--------------------------------------------------------------------------
    | ADMIN ACTIONS
    |--------------------------------------------------------------------------
    */

    elseif (
        in_array(
            $action,
            [
                'start_review',
                'approve',
                'request_revision',
                'reject'
            ],
            true
        )
        &&
        !$errors
    ) {

        if (
            $user['role']
            !== 'admin'
        ) {

            $errors[] =
                'Only the Administrator may perform evaluation actions.';
        }


        /*
        |--------------------------------------------------------------------------
        | NEW RPS WORKFLOW PROTECTION
        |--------------------------------------------------------------------------
        |
        | New/integrated records are processed by RPS.
        | The legacy Admin evaluation actions are retained only
        | for legacy records so the two workflows cannot conflict.
        |
        */

        if (
            isDetailedResearchWorkflow(
                $research
            )
        ) {

            $errors[] =
                'This research record is managed through the RPS workflow. Processing actions must be completed by Research Processing Staff.';
        }


        $adminRemarks =
            trim(
                $_POST['admin_remarks']
                ?? ''
            );


        $oldStatus =
            $research['status'];

        $newStatus = '';
        $decision = '';
        $successMessage = '';


        /*
        |--------------------------------------------------------------------------
        | START REVIEW
        |--------------------------------------------------------------------------
        */

        if (
            $action
            === 'start_review'
        ) {

            if (
                $oldStatus
                !== 'Submitted'
            ) {

                $errors[] =
                    'Only Submitted proposals can be placed Under Review.';
            }


            $newStatus =
                'Under Review';

            $decision =
                'Under Review';


            if (
                $adminRemarks === ''
            ) {

                $adminRemarks =
                    'Administrative review started.';
            }


            $successMessage =
                'Research proposal is now under review.';
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVE
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'approve'
        ) {

            if (
                $oldStatus
                !== 'Under Review'
            ) {

                $errors[] =
                    'Only proposals Under Review can be approved.';
            }


            $newStatus =
                'Approved';

            $decision =
                'Approved';

            $successMessage =
                'Research proposal approved successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | REVISION
        |--------------------------------------------------------------------------
        */

        if (
            $action
            === 'request_revision'
        ) {

            if (
                $oldStatus
                !== 'Under Review'
            ) {

                $errors[] =
                    'Only proposals Under Review can be returned for revision.';
            }


            $newStatus =
                'For Revision';

            $decision =
                'For Revision';

            $successMessage =
                'Research proposal returned for revision.';
        }


        /*
        |--------------------------------------------------------------------------
        | REJECT
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'reject'
        ) {

            if (
                $oldStatus
                !== 'Under Review'
            ) {

                $errors[] =
                    'Only proposals Under Review can be rejected.';
            }


            // A final rejection is immediately archived; no revision pathway follows.
            $newStatus =
                'Archived';

            $decision =
                'Rejected';

            $successMessage =
                'Research proposal rejected and archived.';
        }


        /*
        |--------------------------------------------------------------------------
        | REMARKS
        |--------------------------------------------------------------------------
        */

        if (
            $action !== 'start_review'
            &&
            $adminRemarks === ''
        ) {

            $errors[] =
                'Evaluation remarks are required.';
        }


        if (
            mb_strlen(
                $adminRemarks
            ) > 5000
        ) {

            $errors[] =
                'Evaluation remarks must not exceed 5,000 characters.';
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE ADMIN DECISION
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            try {

                $pdo->beginTransaction();


                if (
                    $newStatus
                    === 'Approved'
                ) {

                    $update =
                        $pdo->prepare("
                            UPDATE research_proposals

                            SET
                                status = ?,
                                approved_at = NOW()

                            WHERE research_id = ?
                              AND status = ?
                        ");

                } elseif ($action === 'reject') {

                    // Existing records and links are retained for audit/reference.
                    $update = $pdo->prepare("
                        UPDATE research_proposals
                        SET status = 'Archived',
                            workflow_status = 'REJECTED',
                            workflow_stage = 'ARCHIVED',
                            is_archived = 1,
                            archived_reason = 'Research rejected during administrative review.',
                            archived_at = NOW(),
                            workflow_updated_at = NOW()
                        WHERE research_id = ?
                          AND status = ?
                    ");

                } else {

                    $update =
                        $pdo->prepare("
                            UPDATE research_proposals

                            SET
                                status = ?

                            WHERE research_id = ?
                              AND status = ?
                        ");
                }


                $update->execute(
                    $action === 'reject'
                        ? [$researchId, $oldStatus]
                        : [$newStatus, $researchId, $oldStatus]
                );


                if (
                    $update->rowCount()
                    !== 1
                ) {

                    throw new RuntimeException(
                        'Unable to update research status.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | EVALUATION
                |--------------------------------------------------------------------------
                */

                $evaluation =
                    $pdo->prepare("
                        INSERT INTO research_evaluations
                        (
                            research_id,
                            evaluator_id,
                            decision,
                            remarks
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ");


                $evaluation->execute([

                    $researchId,

                    (int)$user['user_id'],

                    $decision,

                    $adminRemarks !== ''
                        ? $adminRemarks
                        : null

                ]);


                /*
                |--------------------------------------------------------------------------
                | STATUS HISTORY
                |--------------------------------------------------------------------------
                */

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

                    $oldStatus,

                    $newStatus,

                    $action === 'reject'
                        ? 'Rejected by Administrator. Research immediately archived.'
                            . ($adminRemarks !== '' ? ' Remarks: ' . $adminRemarks : '')
                        : ($adminRemarks !== '' ? $adminRemarks : null)

                ]);


                $notificationTitle =
                    match ($newStatus) {
                        'Under Review' => 'Research proposal under review',
                        'Approved' => 'Research proposal approved',
                        'For Revision' => 'Research proposal needs revision',
                        'Rejected' => 'Research proposal rejected',
                        'Archived' => 'Research proposal rejected and archived',
                        default => 'Research proposal status updated'
                    };


                $notificationMessage = $action === 'reject'
                    ? 'Your research proposal "' . $research['research_title']
                        . '" was rejected and automatically archived. You may submit a new proposal.'
                    : 'Your research proposal "' . $research['research_title']
                        . '" was updated from ' . $oldStatus . ' to ' . $newStatus . '.';


                if ($adminRemarks !== '') {

                    $notificationMessage .=
                        ' Remarks: ' .
                        $adminRemarks;
                }


                createNotification(
                    $pdo,
                    (int)$research['researcher_id'],
                    $notificationTitle,
                    $notificationMessage,
                    'research',
                    $researchId,
                    BASE_URL . '/research/view.php?id=' . $researchId
                );


                $pdo->commit();


                setFlash(
                    'success',
                    $successMessage
                );


                redirect(
                    BASE_URL .
                    '/research/view.php?id=' .
                    $researchId
                );


            } catch (
                Throwable $e
            ) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }


                $errors[] =
                    'Unable to record the evaluation. Please try again.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| RELOAD RESEARCH
|--------------------------------------------------------------------------
*/

$research =
    loadResearchProposal(
        $pdo,
        $researchId
    );


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
                $research['middle_name']
            )
                ? $research['middle_name']
                . ' '
                : ''
        )
        .
        $research['last_name']
    );


/*
|--------------------------------------------------------------------------
| STATUS HISTORY
|--------------------------------------------------------------------------
*/

$historyStmt =
    $pdo->prepare("
        SELECT

            rsh.history_id,
            rsh.old_status,
            rsh.new_status,
            rsh.remarks,
            rsh.changed_at,

            u.first_name,
            u.middle_name,
            u.last_name

        FROM research_status_history rsh

        INNER JOIN users u
            ON u.user_id = rsh.changed_by

        WHERE rsh.research_id = ?

        ORDER BY
            rsh.changed_at DESC,
            rsh.history_id DESC
    ");


$historyStmt->execute([
    $researchId
]);


$statusHistory =
    $historyStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

// Until this RPS confirmation is recorded, the permit-generation form is hidden.
$terminalReportRpsConfirmed =
    ($research['workflow_status'] ?? '') === 'TERMINAL_REPORT_SUBMITTED'
    && researchTerminalReportConfirmed($pdo, $researchId);

$latestNoticeToProceedHistory = null;

foreach (
    $statusHistory
    as
    $historyItem
) {

    $historyRemarks =
        strtolower(
            (string)(
                $historyItem['remarks']
                ?? ''
            )
        );

    if (
        str_contains(
            $historyRemarks,
            'notice to proceed issued'
        )
        ||
        str_contains(
            $historyRemarks,
            'notice to proceed generated'
        )
    ) {

        $latestNoticeToProceedHistory = $historyItem;
        break;
    }
}


/*
|--------------------------------------------------------------------------
| EVALUATIONS
|--------------------------------------------------------------------------
*/

$evaluationStmt =
    $pdo->prepare("
        SELECT

            re.evaluation_id,
            re.decision,
            re.remarks,
            re.evaluated_at,

            u.first_name,
            u.middle_name,
            u.last_name

        FROM research_evaluations re

        INNER JOIN users u
            ON u.user_id = re.evaluator_id

        WHERE re.research_id = ?

        ORDER BY
            re.evaluated_at DESC,
            re.evaluation_id DESC
    ");


$evaluationStmt->execute([
    $researchId
]);


$evaluations =
    $evaluationStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| LATEST UREC / IACUC REVIEW RESULT
|--------------------------------------------------------------------------
*/

$latestEthicsReview = null;


try {

    $ethicsReviewStmt =
        $pdo->prepare("
            SELECT
                rp.ethics_result AS result,
                rp.ethics_remarks AS remarks,
                rp.ethics_reviewed_at AS reviewed_at,

                u.first_name,
                u.middle_name,
                u.last_name

            FROM research_proposals rp

            LEFT JOIN users u
                ON u.user_id = rp.ethics_reviewed_by

            WHERE rp.research_id = ?
              AND rp.ethics_result IS NOT NULL
              AND rp.ethics_result <> ''

            LIMIT 1
        ");


    $ethicsReviewStmt->execute([
        $researchId
    ]);


    $latestEthicsReview =
        $ethicsReviewStmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;


} catch (Throwable $e) {

    error_log(
        'Load UREC/IACUC Review Error: '
        . $e->getMessage()
    );

    $latestEthicsReview = null;
}


function ethicsResultLabel(
    ?string $result
): string {

    return match (
        strtoupper(
            (string)$result
        )
    ) {
        'EXEMPT' => 'Exempt',
        'EXPEDITED' => 'Expedited',
        'FULL_BOARD' => 'Full Board',
        default => $result ?: 'Not recorded'
    };
}


function ethicsClassificationDescription(
    ?string $result
): string {

    return match (
        strtoupper(
            (string)$result
        )
    ) {
        'EXEMPT' =>
            'The manuscript is classified as EXEMPT, indicating that the study does not require full ethics review based on the nature and scope of the submitted research.',

        'EXPEDITED' =>
            'The manuscript is classified as EXPEDITED, indicating that the study requires a limited review process based on the nature and level of risk involved in the submitted research.',

        'FULL_BOARD' =>
            'The manuscript is classified as FULL BOARD, indicating that the study requires review and deliberation by the full committee before further research processing.',

        default =>
            'The UREC/IACUC classification description is not available.'
    };
}


/*
|--------------------------------------------------------------------------
| SYSTEM-GENERATED UREC / IACUC CERTIFICATE VIEW
|--------------------------------------------------------------------------
*/

$isGeneratedEthicsCertificateView =
    isset($_GET['certificate'])
    &&
    $_GET['certificate'] === 'urec_iacuc';

if (
    $isGeneratedEthicsCertificateView
    &&
    !$latestEthicsReview
) {

    setFlash(
        'danger',
        'UREC/IACUC certificate is not available yet.'
    );

    redirect(
        BASE_URL . '/research/view.php?id=' . $researchId
    );
}

$generatedEthicsCertificateNumber =
    'UREC-IACUC-' .
    date('Y') .
    '-' .
    str_pad(
        (string)$researchId,
        5,
        '0',
        STR_PAD_LEFT
    );

$generatedEthicsCertificateUrl =
    BASE_URL .
    '/research/view.php?id=' .
    $researchId .
    '&certificate=urec_iacuc';


/*
|--------------------------------------------------------------------------
| SYSTEM-GENERATED NOTICE TO PROCEED VIEW
|--------------------------------------------------------------------------
*/

$isGeneratedNoticeToProceedView =
    isset($_GET['certificate'])
    &&
    $_GET['certificate'] === 'notice_to_proceed';

if (
    $isGeneratedNoticeToProceedView
    &&
    (
        (string)(
            $research['workflow_status']
            ?? ''
        )
        !== 'NTP_ISSUED'
    )
) {

    setFlash(
        'danger',
        'Notice to Proceed is not available yet.'
    );

    redirect(
        BASE_URL . '/research/view.php?id=' . $researchId
    );
}

$generatedNoticeToProceedNumber =
    'NTP-' .
    date('Y') .
    '-' .
    str_pad(
        (string)$researchId,
        5,
        '0',
        STR_PAD_LEFT
    );

$generatedNoticeToProceedUrl =
    BASE_URL .
    '/research/view.php?id=' .
    $researchId .
    '&certificate=notice_to_proceed';

$noticeToProceedDateRecorded =
    !empty($latestNoticeToProceedHistory['changed_at'])
        ? $latestNoticeToProceedHistory['changed_at']
        : (
            !empty($research['workflow_updated_at'])
                ? $research['workflow_updated_at']
                : (
                    !empty($research['approved_at'])
                        ? $research['approved_at']
                        : date('Y-m-d H:i:s')
                )
        );

$noticeToProceedRecordedBy =
    trim(
        (string)(
            $latestNoticeToProceedHistory['first_name']
            ?? ''
        )
        . ' '
        .
        (string)(
            $latestNoticeToProceedHistory['middle_name']
            ?? ''
        )
        . ' '
        .
        (string)(
            $latestNoticeToProceedHistory['last_name']
            ?? ''
        )
    );

/*
|--------------------------------------------------------------------------
| NOTICE TO PROCEED DISPLAY NAME
|--------------------------------------------------------------------------
| The NTP document should not display "Unknown" when the recorder name is
| missing. For the generated document, show the office role instead.
*/

$noticeToProceedRecordedByClean =
    strtolower(
        trim(
            $noticeToProceedRecordedBy
        )
    );

if (
    $noticeToProceedRecordedByClean === ''
    ||
    $noticeToProceedRecordedByClean === 'unknown'
    ||
    $noticeToProceedRecordedByClean === 'unknown user'
    ||
    $noticeToProceedRecordedByClean === 'not assigned'
) {

    $noticeToProceedRecordedBy =
        'Research Processing Staff (RPS)';
}



/*
|--------------------------------------------------------------------------
| SYSTEM-GENERATED SELF-INITIATED PERMIT
|--------------------------------------------------------------------------
|
| The green-arrow Self-Initiated flow generates the permit automatically
| after the Terminal Report is uploaded. This is separate from the RPS-issued
| permit used by the regular research workflow.
|
*/

$isGeneratedSelfInitiatedPermitView =
    isset($_GET['certificate'])
    &&
    $_GET['certificate'] === 'self_initiated_permit';

$selfInitiatedPermitAvailable =
    strtoupper((string)($research['submission_track'] ?? '')) === 'SELF_INITIATED'
    &&
    $hasSelfInitiatedTerminalReportDocument
    &&
    in_array(
        (string)($research['workflow_status'] ?? ''),
        [
            'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED',
            'SELF_INITIATED_PERMIT_GENERATED',
            'SELF_INITIATED_COMPLETED'
        ],
        true
    );

if (
    $isGeneratedSelfInitiatedPermitView
    &&
    !$selfInitiatedPermitAvailable
) {
    setFlash(
        'danger',
        'The Self-Initiated Permit to Publish / Present is not available yet.'
    );

    redirect(
        BASE_URL . '/research/view.php?id=' . $researchId
    );
}

$selfInitiatedPermitIssuedAt =
    !empty($selfInitiatedTerminalReportDocument['uploaded_at'])
        ? (string)$selfInitiatedTerminalReportDocument['uploaded_at']
        : (
            !empty($research['workflow_updated_at'])
                ? (string)$research['workflow_updated_at']
                : date('Y-m-d H:i:s')
        );

$selfInitiatedPermitNumber =
    'SI-PERMIT-'
    . date('Y', strtotime($selfInitiatedPermitIssuedAt))
    . '-'
    . str_pad((string)$researchId, 5, '0', STR_PAD_LEFT);

$selfInitiatedPermitUrl =
    BASE_URL
    . '/research/view.php?id='
    . $researchId
    . '&certificate=self_initiated_permit';





/*
|--------------------------------------------------------------------------
| PUBLICATION + PRESENTATION PERMITS (REGULAR FOR-URRC)
|--------------------------------------------------------------------------
| The new RPS Generate Both Permits action creates ONE audit marker for the
| pair. Legacy single permits remain single-type; a URL parameter cannot
| invent a second permit that RPS never authorized.
*/

$latestPermitEvaluation = null;
foreach ($evaluations as $evaluation) {
    if (str_starts_with((string)($evaluation['decision'] ?? ''), 'Permit Issued - ')) {
        $latestPermitEvaluation = $evaluation;
        break;
    }
}

$latestPermitHistory = null;
$bothPermitsIssued = false;
$issuedPermitTypes = [];
foreach ($statusHistory as $historyItem) {
    $historyText = strtoupper((string)($historyItem['remarks'] ?? ''));
    if (str_contains($historyText, '[PERMIT_TYPE:BOTH]')
        && str_contains($historyText, 'PERMIT FOR PUBLICATION AND PRESENTATION ISSUED')) {
        $bothPermitsIssued = true;
        $issuedPermitTypes = ['PUBLICATION', 'PRESENTATION'];
        $latestPermitHistory ??= $historyItem;
        break;
    }
    if (str_contains($historyText, '[PERMIT_TYPE:PUBLICATION]')
        || str_contains($historyText, 'PERMIT FOR PUBLICATION ISSUED')) {
        $issuedPermitTypes['PUBLICATION'] = 'PUBLICATION';
        $latestPermitHistory ??= $historyItem;
    }
    if (str_contains($historyText, '[PERMIT_TYPE:PRESENTATION]')
        || str_contains($historyText, 'PERMIT FOR PRESENTATION ISSUED')) {
        $issuedPermitTypes['PRESENTATION'] = 'PRESENTATION';
        $latestPermitHistory ??= $historyItem;
    }
}

$requestedPermitType = strtoupper(trim((string)($_GET['permit_type'] ?? '')));
if (!in_array($requestedPermitType, ['PUBLICATION', 'PRESENTATION'], true)) {
    $requestedPermitType = '';
}

$legacyPermitType = '';
if (!$bothPermitsIssued && $latestPermitEvaluation) {
    $evaluationType = strtoupper(trim(substr(
        (string)$latestPermitEvaluation['decision'],
        strlen('Permit Issued - ')
    )));
    if (in_array($evaluationType, ['PUBLICATION', 'PRESENTATION'], true)) {
        $legacyPermitType = $evaluationType;
        $issuedPermitTypes[$evaluationType] = $evaluationType;
    }
}

$availablePermitTypes = $bothPermitsIssued
    ? ['PUBLICATION', 'PRESENTATION']
    : array_values($issuedPermitTypes);
$permitType = $requestedPermitType !== '' ? $requestedPermitType
    : ($legacyPermitType !== '' ? $legacyPermitType
        : ($availablePermitTypes[0] ?? ''));
$permitTypeLabel = match ($permitType) {
    'PUBLICATION' => 'Publication',
    'PRESENTATION' => 'Presentation',
    default => 'Publication / Presentation'
};

$permitIssuedDateRecorded =
    !empty($latestPermitHistory['changed_at'])
        ? $latestPermitHistory['changed_at']
        : (!empty($latestPermitEvaluation['evaluated_at'])
            ? $latestPermitEvaluation['evaluated_at']
            : (!empty($research['workflow_updated_at'])
                ? $research['workflow_updated_at']
                : date('Y-m-d H:i:s')));

$permitIssuedBy = trim(
    (string)($latestPermitHistory['first_name'] ?? $latestPermitEvaluation['first_name'] ?? '')
    . ' ' . (string)($latestPermitHistory['middle_name'] ?? $latestPermitEvaluation['middle_name'] ?? '')
    . ' ' . (string)($latestPermitHistory['last_name'] ?? $latestPermitEvaluation['last_name'] ?? '')
);
if ($permitIssuedBy === '') {
    $permitIssuedBy = 'Research Processing Staff (RPS)';
}

$permitRemarksDisplay = trim((string)($latestPermitEvaluation['remarks'] ?? ''));
if ($permitRemarksDisplay === '' && $latestPermitHistory) {
    $historyPermitRemarks = (string)($latestPermitHistory['remarks'] ?? '');
    if (preg_match('/\bRemarks:\s*(.+)$/is', $historyPermitRemarks, $permitRemarkMatch)) {
        $permitRemarksDisplay = trim($permitRemarkMatch[1]);
    }
}

$isGeneratedPermitView = isset($_GET['certificate'])
    && $_GET['certificate'] === 'publication_presentation_permit';

if ($isGeneratedPermitView && (
    !in_array((string)($research['workflow_status'] ?? ''), ['PERMIT_ISSUED', 'COMPLETED'], true)
    || $permitType === ''
    || !in_array($permitType, $availablePermitTypes, true)
)) {
    setFlash('danger', 'The requested research permit has not been issued.');
    redirect(BASE_URL . '/research/view.php?id=' . $researchId);
}

$generatedPermitNumber =
    'PERMIT-'
    . date('Y', strtotime($permitIssuedDateRecorded))
    . '-'
    . str_pad((string)$researchId, 5, '0', STR_PAD_LEFT)
    . ($bothPermitsIssued
        ? ($permitType === 'PRESENTATION' ? '-PRES' : '-PUB')
        : '');

$publicationPermitUrl = BASE_URL . '/research/view.php?id=' . $researchId
    . '&certificate=publication_presentation_permit&permit_type=publication';
$presentationPermitUrl = BASE_URL . '/research/view.php?id=' . $researchId
    . '&certificate=publication_presentation_permit&permit_type=presentation';
$generatedPermitUrl = BASE_URL . '/research/view.php?id=' . $researchId
    . '&certificate=publication_presentation_permit'
    . ($permitType !== '' ? '&permit_type=' . strtolower($permitType) : '');


/*
|--------------------------------------------------------------------------
| LATEST URRC REVIEW RESULT
|--------------------------------------------------------------------------
*/

$latestUrrcReview = null;

foreach (
    $evaluations
    as
    $evaluation
) {

    if (
        str_starts_with(
            (string)$evaluation['decision'],
            'URRC '
        )
    ) {

        $latestUrrcReview = $evaluation;
        break;
    }
}


/*
|--------------------------------------------------------------------------
| LATEST IN-HOUSE REVIEW RESULT
|--------------------------------------------------------------------------
*/

$latestInhouseReview = null;

foreach (
    $evaluations
    as
    $evaluation
) {

    if (
        str_starts_with(
            (string)$evaluation['decision'],
            'In-House '
        )
    ) {

        $latestInhouseReview = $evaluation;
        break;
    }
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
| PROGRESS INFORMATION
|--------------------------------------------------------------------------
*/

$progressPercentage =
    min(
        100,
        max(
            0,
            (int)$research[
                'progress_percentage'
            ]
        )
    );


$implementationLabel =
    'Not Started';


if (
    $research['status']
    === 'Ongoing'
) {

    if (
        $progressPercentage >= 100
    ) {

        $implementationLabel =
            'Implementation Complete';

    } elseif (
        $progressPercentage >= 75
    ) {

        $implementationLabel =
            'Advanced Progress';

    } elseif (
        $progressPercentage >= 50
    ) {

        $implementationLabel =
            'Midway Progress';

    } elseif (
        $progressPercentage >= 25
    ) {

        $implementationLabel =
            'Implementation in Progress';

    } elseif (
        $progressPercentage > 0
    ) {

        $implementationLabel =
            'Early Implementation';
    }
}


/*
|--------------------------------------------------------------------------
| WORKFLOW DISPLAY INFORMATION
|--------------------------------------------------------------------------
|
| This controls the green status strip at the top of the details page.
| It shows the current queue/stage while the research is under review,
| then shows the actual implementation progress after NTP is issued.
|
*/

$currentWorkflowStatus =
    strtoupper(
        trim(
            (string)(
                $research[
                    'workflow_status'
                ]
                ?? ''
            )
        )
    );

$currentWorkflowStage =
    strtoupper(
        trim(
            (string)(
                $research[
                    'workflow_stage'
                ]
                ?? ''
            )
        )
    );

$workflowDisplay =
    match ($currentWorkflowStatus) {
        'AWAITING_PATHWAY_SELECTION' => [
            'label' => 'Choose Research Pathway',
            'description' => 'Faculty must choose Self-Initiated or For URRC before review begins.',
            'stage' => 'Pathway Selection'
        ],

        'FOR_URRC_PATH_SELECTED' => [
            'label' => 'Awaiting Initial RPS Processing',
            'description' => 'For URRC selected. RPS must forward this proposal from New Research Submissions before committee review.',
            'stage' => 'New Research Submission'
        ],

        'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS' => [
            'label' => 'UPE Certificates Submitted',
            'description' => 'Both UPE links are recorded. UREC/IACUC review is the next stage.',
            'stage' => 'UREC/IACUC Pending'
        ],

        'SUBMITTED' => [
            'label' => 'New Research Submission',
            'description' => 'Waiting for RPS to start UREC / IACUC processing.',
            'stage' => 'Submission Queue'
        ],

        'FOR_ETHICS_REVIEW' => [
            'label' => 'UREC / IACUC Processing',
            'description' => 'RPS is recording the ethics classification for this research.',
            'stage' => 'Ethics Queue'
        ],

        'ETHICS_COMPLETED' => [
            'label' => 'UREC / IACUC Completed',
            'description' => 'Researcher may continue the research to the next workflow step.',
            'stage' => 'Ethics Completed'
        ],

        'SELF_INITIATED_UPE_REQUIRED' => [
            'label' => 'UPE Certificates Required',
            'description' => 'Self-initiated research must submit certificates from UPE members.',
            'stage' => 'UPE Requirement'
        ],

        'SELF_INITIATED_UPE_SUBMITTED' => [
            'label' => 'UPE Certificates Submitted',
            'description' => 'The researcher may now upload the completed research / whole manuscript.',
            'stage' => 'UPE Submitted'
        ],

        'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED' => [
            'label' => 'Completed Research Uploaded',
            'description' => 'The completed research / whole manuscript has been submitted for the Self-Initiated workflow.',
            'stage' => 'Completed Research'
        ],

        'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED' => [
            'label' => 'Permit Generated',
            'description' => 'The Terminal Report is complete and the system-generated Permit to Publish / Present is available.',
            'stage' => 'Permit Generated'
        ],

        'SELF_INITIATED_PERMIT_GENERATED' => [
            'label' => 'Permit Generated',
            'description' => 'The system-generated Permit to Publish / Present is available. Upload proof of publication or presentation to complete the workflow.',
            'stage' => 'Permit Generated'
        ],

        'SELF_INITIATED_COMPLETED' => [
            'label' => 'Self-Initiated Research Completed',
            'description' => 'The publication file has been uploaded and the Self-Initiated workflow is complete.',
            'stage' => 'Completed'
        ],

        'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED' => [
            'label' => 'Terminal Report Submitted',
            'description' => 'The Self-Initiated Terminal Report has been submitted. The next step is system-generated permit processing.',
            'stage' => 'Terminal Report'
        ],

        'FOR_URRC' => [
            'label' => 'URRC Review',
            'description' => 'Waiting for RPS to record the URRC result before In-House review.',
            'stage' => 'URRC Queue'
        ],

        'URRC_REVISION_REQUIRED' => [
            'label' => 'URRC Revision Required',
            'description' => 'Researcher must upload a revised manuscript before review can continue.',
            'stage' => 'URRC Revision'
        ],

        'URRC_REVISED_SUBMITTED' => [
            'label' => 'URRC Revised Manuscript Submitted',
            'description' => 'Waiting for RPS to review the revised manuscript for URRC.',
            'stage' => 'URRC Queue'
        ],

        'URRC_REJECTED' => [
            'label' => 'Archived after URRC Review',
            'description' => 'The research did not pass the URRC review stage.',
            'stage' => 'Archived'
        ],

        'URRC_DID_NOT_REVISE' => [
            'label' => 'Archived - Did Not Revise',
            'description' => 'The required URRC revised manuscript was not submitted.',
            'stage' => 'Archived'
        ],

        'FOR_INHOUSE' => [
            'label' => 'In-House Review',
            'description' => 'Waiting for RPS to encode the In-House review result.',
            'stage' => 'In-House Queue'
        ],

        'INHOUSE_REVISION_REQUIRED' => [
            'label' => 'In-House Revision Required',
            'description' => 'Researcher must submit the revised In-House manuscript within 180 days of the Passed with Revision decision.',
            'stage' => 'In-House Revision'
        ],

        'INHOUSE_REVISED_SUBMITTED' => [
            'label' => 'In-House Revised Manuscript Submitted',
            'description' => 'Waiting for RPS to review the revised manuscript for In-House.',
            'stage' => 'In-House Queue'
        ],

        'INHOUSE_DID_NOT_REVISE' => [
            'label' => 'Archived - In-House Did Not Revise',
            'description' => 'The required In-House revision was not resubmitted within 180 days.',
            'stage' => 'Archived'
        ],

        'INHOUSE_FAILED' => [
            'label' => 'Archived after In-House Review',
            'description' => 'The research failed the In-House review stage.',
            'stage' => 'Archived'
        ],

        'READY_FOR_NTP' => [
            'label' => 'Ready for Notice to Proceed',
            'description' => 'In-House review is passed. RPS may issue the Notice to Proceed.',
            'stage' => 'NTP Queue'
        ],

        'NTP_ISSUED' => [
            'label' => 'Implementation / Ongoing',
            'description' => $implementationLabel,
            'stage' => 'Implementation'
        ],

        'COMPLETED_MANUSCRIPT_SUBMITTED' => [
            'label' => 'Completed Manuscript Submitted',
            'description' => 'Researcher has uploaded the completed manuscript for checking.',
            'stage' => 'Completion'
        ],

        'FOR_TERMINAL_REPORT' => [
            'label' => 'For Terminal Report',
            'description' => 'Completed manuscript has been confirmed. Waiting for the terminal report requirement.',
            'stage' => 'Terminal Report'
        ],

        'TERMINAL_REPORT_SUBMITTED' => [
            'label' => 'Terminal Report Submitted',
            'description' => 'Waiting for RPS to process the permit for publish or presentation.',
            'stage' => 'Permit Queue'
        ],

        'PERMIT_ISSUED' => [
            'label' => 'Permit Issued',
            'description' => 'Permit for publication or presentation has been issued.',
            'stage' => 'Publication'
        ],

        'COMPLETED' => [
            'label' => 'Completed Research',
            'description' => 'Research workflow is completed and retained in the system records.',
            'stage' => 'Completed'
        ],

        'ARCHIVED_NO_REVISION' => [
            'label' => 'Archived: No Revision Submitted',
            'description' => 'Required revision was not submitted within the expected process.',
            'stage' => 'Archived'
        ],

        default => [
            'label' => match ((string)$research['status']) {
                'Draft' => 'Draft Proposal',
                'Submitted' => 'Submitted Proposal',
                'Under Review' => 'Under Review',
                'For Revision' => 'For Revision',
                'Approved' => 'Approved Research',
                'Ongoing' => 'Implementation / Ongoing',
                'Completed' => 'Completed Research',
                'Rejected' => 'Rejected Research',
                'Archived' => 'Archived Research',
                default => 'Research Workflow'
            },
            'description' => match ((string)$research['status']) {
                'Draft' => 'This proposal is still being prepared by the faculty researcher.',
                'Submitted' => 'Waiting for the Research Office to begin processing.',
                'Under Review' => 'The research is currently being processed by the Research Office.',
                'For Revision' => 'Researcher must revise and resubmit the manuscript.',
                'Approved' => 'Approved and ready for implementation monitoring.',
                'Ongoing' => $implementationLabel,
                'Completed' => 'Research has been completed.',
                'Rejected' => 'Research was not approved.',
                'Archived' => 'Research record is archived.',
                default => 'Current workflow information is shown here.'
            },
            'stage' => $currentWorkflowStage !== ''
                ? str_replace('_', ' ', $currentWorkflowStage)
                : 'Workflow Status'
        ]
    };

// Earlier Step 3 records selected For URRC before an initial RPS intake
// action existed. Keep those submissions in intake until an RPS forwards them.
if (researchUrrcAwaitingRpsIntake($pdo, $researchId, $research)) {
    $workflowDisplay = [
        'label' => 'Awaiting Initial RPS Processing',
        'description' => 'For URRC selected. Waiting in New Research Submissions for RPS to forward it to URRC Review.',
        'stage' => 'New Research Submission'
    ];
}


$showImplementationProgress =
    in_array(
        $currentWorkflowStatus,
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
        (string)$research['status'],
        [
            'Ongoing',
            'Completed'
        ],
        true
    );

if (
    (string)$research['status'] === 'Completed'
    ||
    $currentWorkflowStatus === 'COMPLETED'
) {
    $progressPercentage = 100;
    $implementationLabel = 'Research implementation completed.';
    $workflowDisplay['description'] = 'Research implementation completed.';
}

$workflowBadgeText =
    $showImplementationProgress
        ? $progressPercentage . '%'
        : $workflowDisplay['stage'];


/*
|--------------------------------------------------------------------------
| STATUS CLASS + ICON
|--------------------------------------------------------------------------
*/

$statusClass = 'status-draft';
$statusIcon = 'bi-pencil-square';


switch (
    $research['status']
) {

    case 'Submitted':

        $statusClass =
            'status-submitted';

        $statusIcon =
            'bi-send-check';

        break;


    case 'Under Review':

        $statusClass =
            'status-review';

        $statusIcon =
            'bi-search';

        break;


    case 'For Revision':

        $statusClass =
            'status-revision';

        $statusIcon =
            'bi-arrow-repeat';

        break;


    case 'Approved':

        $statusClass =
            'status-approved';

        $statusIcon =
            'bi-check-circle';

        break;


    case 'Ongoing':

        $statusClass =
            'status-ongoing';

        $statusIcon =
            'bi-graph-up-arrow';

        break;


    case 'Completed':

        $statusClass =
            'status-completed';

        $statusIcon =
            'bi-award';

        break;


    case 'Rejected':

        $statusClass =
            'status-rejected';

        $statusIcon =
            'bi-x-circle';

        break;


    case 'Archived':

        $statusClass =
            'status-archived';

        $statusIcon =
            'bi-archive';

        break;
}


/*
|--------------------------------------------------------------------------
| FILE SIZE
|--------------------------------------------------------------------------
*/

function researchFileSize(
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
| CONSUME FLASH
|--------------------------------------------------------------------------
|
| We still clear flash messages after redirects,
| but they are not displayed as temporary banners
| on this page.
|
*/

/*
|--------------------------------------------------------------------------
| HIDE INTERNAL PROCESSING ERRORS FROM THE USER INTERFACE
|--------------------------------------------------------------------------
|
| Technical failures are still written to the PHP error log by the catch
| blocks above, but generic/internal "Unable..." messages are not displayed
| as red notifications to end users.
|
*/

$visibleErrors = array_values(
    array_filter(
        $errors,
        static function ($message): bool {

            $message = trim((string)$message);

            if ($message === '') {
                return false;
            }

            return !preg_match(
                '/^(Unable\b|Research Submission Error:|UREC\/IACUC Database Error:)/i',
                $message
            );
        }
    )
);

$flash = getFlash();


require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';

?>


<style>

@import url("https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700;800&display=swap");

/* =========================================================
   PAGE
========================================================= */

.research-view {
    max-width: 1200px;
    margin: 0 auto;
}


.research-view-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;
}


.research-view-heading h1 {
    margin: 0 0 5px;

    font-size: 25px;
    font-weight: 700;
}


.research-view-heading p {
    margin: 0;

    color: var(--asu-muted);

    font-size: 11px;
}


/* =========================================================
   ERROR
========================================================= */

.research-errors {
    margin-bottom: 18px;

    padding: 14px 17px;

    border:
        1px solid #edcccc;

    border-radius: 10px;

    background: #fff6f6;

    color: #873b3b;

    font-size: 11px;
}


/* =========================================================
   SUMMARY
========================================================= */

.research-summary {
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


.research-summary-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;
}


.research-summary h2 {
    max-width: 850px;

    margin: 0 0 9px;

    color: #ffffff;

    font-size: 20px;
    font-weight: 700;

    line-height: 1.4;
}


.research-summary-meta {
    display: flex;
    flex-wrap: wrap;

    gap: 8px 18px;

    color:
        rgba(
            255,
            255,
            255,
            .77
        );

    font-size: 10px;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.research-status {
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


.status-draft {
    background: #eef1ef;
    color: #626b64;
}


.status-submitted {
    background: #e8f0fd;
    color: #365f97;
}


.status-review {
    background: #fff3c4;
    color: #79620d;
}


.status-revision {
    background: #fff0e2;
    color: #99541b;
}


.status-approved,
.status-ongoing,
.status-completed {

    background: #e4f4e8;
    color: #23703e;
}


.status-rejected {
    background: #fbe6e6;
    color: #9e3939;
}


.status-archived {
    background: #e9ebed;
    color: #62676c;
}


/* =========================================================
   IMPLEMENTATION PROGRESS
========================================================= */

.research-progress {
    margin-top: 18px;

    padding-top: 15px;

    border-top:
        1px solid
        rgba(
            255,
            255,
            255,
            .15
        );
}


.research-progress-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 15px;

    margin-bottom: 7px;

    font-size: 9px;
}


.research-progress-head strong {
    color: #ffd21c;

    font-size: 12px;
}


.research-progress-subtitle {
    display: block;

    margin-top: 3px;

    color:
        rgba(
            255,
            255,
            255,
            .63
        );

    font-size: 8px;
}


.research-progress-bar {
    height: 7px;

    overflow: hidden;

    border-radius: 50px;

    background:
        rgba(
            255,
            255,
            255,
            .17
        );
}


.research-progress-value {
    height: 100%;

    border-radius: 50px;

    background: #ffd21c;
}


/* =========================================================
   CURRENT RESEARCH STATUS
========================================================= */

.research-status-overview {
    display: flex;
    align-items: center;

    gap: 15px;

    margin-bottom: 20px;

    padding: 17px 19px;

    background: #ffffff;

    border:
        1px solid
        var(--asu-border);

    border-radius: 13px;
}


.research-status-overview-icon {
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


.research-status-overview-content {
    flex: 1;

    min-width: 0;
}


.research-status-label {
    display: block;

    margin-bottom: 2px;

    color: #8a938c;

    font-size: 8px;
    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .05em;
}


.research-status-overview h3 {
    margin: 0 0 4px;

    color: #293129;

    font-size: 14px;
    font-weight: 700;
}


.research-status-overview p {
    max-width: 720px;

    margin: 0;

    color: #727c74;

    font-size: 9px;

    line-height: 1.65;
}


/* =========================================================
   GRID
========================================================= */

.research-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.6fr)
        minmax(280px, .75fr);

    gap: 20px;

    align-items: start;
}


.research-column {
    display: grid;

    gap: 20px;
}


/* =========================================================
   PANEL
========================================================= */

.research-panel {
    overflow: hidden;

    background: #ffffff;

    border:
        1px solid
        var(--asu-border);

    border-radius: 14px;
}


.research-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    min-height: 60px;

    padding: 15px 20px;

    border-bottom:
        1px solid #edf1ed;
}


.research-panel-title {
    display: flex;
    align-items: center;

    gap: 9px;

    margin: 0;

    color: #283029;

    font-size: 13px;
    font-weight: 700;
}


.research-panel-icon {
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


.research-panel-body {
    padding: 20px;
}


/* =========================================================
   INFO
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

    border-bottom:
        1px solid #edf1ed;
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
    transition: background .18s ease, border-color .18s ease, transform .18s ease;
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

.timeline-item,
.evaluation-item {

    padding: 12px 0;

    border-bottom:
        1px solid #edf1ed;
}


.timeline-item:first-child,
.evaluation-item:first-child {

    padding-top: 0;
}


.timeline-item:last-child,
.evaluation-item:last-child {

    border-bottom: 0;

    padding-bottom: 0;
}


.timeline-item strong,
.evaluation-item strong {

    display: block;

    margin-bottom: 4px;

    font-size: 10px;
}


.timeline-item small,
.evaluation-item small {

    display: block;

    color: #8a938c;

    font-size: 8px;
}


.timeline-item p,
.evaluation-item p {

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

    border:
        1px solid #e2e8e2;

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
    max-width: 650px;

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

    border-left:
        3px solid #d7ad23;

    border-radius: 7px;

    background: #fff9e5;

    color: #6e5d23;

    font-size: 9px;

    line-height: 1.6;
}


/* =========================================================
   SELF-INITIATED / UPE STEP
========================================================= */

.ethics-choice-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex-shrink: 0;
}

.ethics-choice-actions form {
    margin: 0;
}

.ethics-choice-actions .btn {
    width: 100%;
    min-width: 145px;
}

.btn-self-initiated {
    border: 1px solid var(--asu-green);
    background: #ffffff;
    color: var(--asu-green);
}

.btn-self-initiated:hover,
.btn-self-initiated:focus {
    border-color: var(--asu-green);
    background: #f0f8f2;
    color: var(--asu-green);
}

.upe-step-card {
    border-left: 4px solid var(--asu-green);
}

.upe-upload-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 16px;
}

.upe-upload-item {
    padding: 16px;
    border: 1px solid #e1e9e2;
    border-radius: 12px;
    background: #fbfdfb;
}

.upe-upload-item.is-complete {
    border-color: #b8d9c1;
    background: #f2faf4;
}

.upe-upload-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}

.upe-upload-head h5 {
    margin: 0 0 3px;
    color: #273129;
    font-size: 11px;
    font-weight: 700;
}

.upe-upload-head p {
    margin: 0;
    color: #7c867e;
    font-size: 9px;
    line-height: 1.5;
}

.upe-upload-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 999px;
    background: #edf3ee;
    color: #68736b;
    font-size: 8px;
    font-weight: 700;
    white-space: nowrap;
}

.upe-upload-item.is-complete .upe-upload-status {
    background: #dff1e4;
    color: #1f6d39;
}

.upe-upload-form {
    display: grid;
    gap: 9px;
}

.upe-upload-form input[type="file"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #dfe6df;
    border-radius: 9px;
    background: #ffffff;
    font-size: 9px;
}

.upe-complete-file {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.upe-complete-file i {
    color: var(--asu-green);
    font-size: 18px;
}

.upe-complete-file div {
    min-width: 0;
    flex: 1;
}

.upe-complete-file strong {
    display: block;
    overflow: hidden;
    color: #2c352e;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.upe-complete-file small {
    color: #7d887f;
    font-size: 8px;
}

.self-next-step {
    margin-top: 14px;
    padding: 12px 14px;
    border: 1px dashed #d2dcd4;
    border-radius: 10px;
    background: #f8faf8;
    color: #6c766f;
    font-size: 9px;
    line-height: 1.6;
}

.self-next-step.is-ready {
    border-style: solid;
    border-color: #b9d8c0;
    background: #f1faf3;
    color: #2e6f40;
}

@media (max-width: 700px) {
    .upe-upload-grid {
        grid-template-columns: 1fr;
    }

    .ethics-choice-actions {
        width: 100%;
    }
}


/* =========================================================
   STATUS HISTORY LENGTH ONLY
========================================================= */

.status-history-panel {
    min-height: 610px;
    height: 610px;

    display: flex;
    flex-direction: column;

    align-self: start;
}


.status-history-panel .research-panel-header {
    flex: 0 0 auto;
}


.status-history-panel .research-panel-body {
    flex: 1 1 auto;

    min-height: 0;

    overflow-y: auto;

    padding-right: 16px;
}


.status-history-panel .research-panel-body::-webkit-scrollbar {
    width: 6px;
}


.status-history-panel .research-panel-body::-webkit-scrollbar-thumb {
    border-radius: 20px;

    background: #cfd8d1;
}


.status-history-panel .research-panel-body::-webkit-scrollbar-track {
    background: transparent;
}



/* =========================================================
   ADMIN REVIEW
========================================================= */

.admin-review-form
.form-label {

    font-size: 10px;

    font-weight: 600;
}


.admin-review-form textarea {
    min-height: 115px;

    font-size: 11px;
}


.admin-review-actions {
    display: flex;
    flex-wrap: wrap;

    gap: 8px;

    margin-top: 12px;
}


/* =========================================================
   DOCUMENT VIEWER
========================================================= */

.research-document-viewer {
    overflow: hidden;

    background: #ffffff;

    border:
        1px solid
        var(--asu-border);

    border-radius: 14px;
}


.research-document-viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 16px;

    padding: 15px 18px;

    border-bottom:
        1px solid #edf1ed;
}


.research-document-viewer-title {
    display: flex;
    align-items: center;

    gap: 11px;

    min-width: 0;
}


.research-document-viewer-title strong {
    display: block;

    overflow: hidden;

    color: #293129;

    font-size: 13px;

    text-overflow: ellipsis;

    white-space: nowrap;
}


.research-document-viewer-title small {
    display: block;

    margin-top: 3px;

    color: #838c85;

    font-size: 9px;
}


.research-document-viewer-actions {
    display: flex;
    align-items: center;

    gap: 8px;

    flex-shrink: 0;
}


.research-document-frame {
    width: 100%;
    min-height: 760px;

    border: 0;

    background: #f8faf8;
}


.research-docx-preview {
    min-height: 650px;

    padding: 28px;

    background: #f8faf8;
}


.research-docx-page {
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


.research-preview-message {
    min-height: 430px;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 34px;

    background: #f8faf8;

    text-align: center;
}


.research-preview-message-box {
    max-width: 520px;

    padding: 28px;

    background: #ffffff;

    border:
        1px solid #e1e8e1;

    border-radius: 13px;
}


.research-preview-message-icon {
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


.research-preview-message-box h3 {
    margin: 0 0 8px;

    color: #293129;

    font-size: 15px;

    font-weight: 700;
}


.research-preview-message-box p {
    margin: 0 0 16px;

    color: #6f7871;

    font-size: 10px;

    line-height: 1.7;
}





/* =========================================================
   PHASE 1 MANUSCRIPT SUMMARY
========================================================= */

.phase-one-summary-panel {
    margin-bottom: 16px;
}

.phase-one-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
}

.phase-one-item {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    min-height: 86px;
    padding: 13px;
    border: 1px solid #e4ebe4;
    border-radius: 13px;
    background: #fbfdfb;
}

.phase-one-item.is-complete {
    border-color: rgba(0, 107, 46, .20);
    background: #f4fbf6;
}

.phase-one-item.is-missing {
    border-color: rgba(242, 140, 40, .30);
    background: #fffaf2;
}

.phase-one-icon {
    width: 34px;
    height: 34px;
    min-width: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: rgba(0, 107, 46, .08);
    color: var(--asu-green);
    font-size: 15px;
}

.phase-one-item.is-missing .phase-one-icon {
    background: rgba(242, 140, 40, .14);
    color: var(--asu-orange-dark);
}

.phase-one-text small {
    display: block;
    margin-bottom: 4px;
    color: #738078;
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: .055em;
    text-transform: uppercase;
}

.phase-one-text strong {
    display: block;
    overflow: hidden;
    color: #1c251f;
    font-size: 11px;
    line-height: 1.45;
    text-overflow: ellipsis;
}

.phase-one-text span {
    display: block;
    margin-top: 2px;
    color: #69746d;
    font-size: 9px;
    line-height: 1.45;
}

.research-image-preview {
    min-height: 650px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 28px;
    background: #f8faf8;
}

.research-image-preview img {
    max-width: 100%;
    height: auto;
    border: 1px solid #dfe8dd;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 12px 30px rgba(21, 55, 28, .08);
}

@media (max-width: 1000px) {
    .phase-one-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .phase-one-grid > :last-child:nth-child(odd) {
        grid-column: 1 / -1;
        width: calc((100% - 12px) / 2);
        justify-self: center;
    }
}

@media (max-width: 700px) {
    .phase-one-grid {
        grid-template-columns: 1fr;
    }
    .phase-one-grid > :last-child:nth-child(odd) {
        grid-column: auto;
        width: 100%;
    }
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width: 1000px
) {

    .research-grid {
        grid-template-columns: 1fr;
    }

}


@media (
    max-width: 700px
) {

    .research-view-heading,
    .research-summary-top,
    .workflow-content {

        flex-direction: column;
    }


    .info-grid {
        grid-template-columns: 1fr;
    }


    .workflow-buttons {
        width: 100%;

        flex-direction: column;
    }


    .workflow-buttons .btn {
        width: 100%;
    }


    .research-status-overview {
        align-items: flex-start;

        flex-wrap: wrap;
    }


    .research-status-overview .btn {
        width: 100%;
    }


    .status-history-panel {
        min-height: 420px;
        height: 420px;
    }

}



/* =========================================================
   RPS PROFESSIONAL MINIMAL UI POLISH
   Design-only overrides. No workflow logic changed.
========================================================= */

.page-content {
    background:
        radial-gradient(circle at top right, rgba(8, 117, 44, .035), transparent 34%),
        linear-gradient(180deg, #f8fbf7 0%, #f3f7f2 100%);
}

.research-view {
    max-width: 1160px;
}

.research-view-heading {
    align-items: center;
    margin-bottom: 16px;
    padding: 18px 20px;
    border: 1px solid #e3ebe3;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 12px 30px rgba(21, 55, 28, .045);
}

.research-view-heading h1 {
    font-size: 24px;
    line-height: 1.18;
    letter-spacing: -.025em;
}

.research-view-heading p {
    max-width: 760px;
    font-size: 12px;
    line-height: 1.55;
}

.research-summary {
    position: relative;
    overflow: hidden;
    margin-bottom: 16px;
    padding: 20px 22px;
    border: 1px solid #e0e9e1;
    border-left: 5px solid #08752c;
    border-radius: 18px;
    background: #ffffff;
    color: #263029;
    box-shadow: 0 12px 30px rgba(21, 55, 28, .045);
}

.research-summary::after {
    content: "";
    position: absolute;
    right: -42px;
    top: -42px;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: rgba(8, 117, 44, .055);
    pointer-events: none;
}

.research-summary h2 {
    color: #243029;
    font-size: 20px;
    letter-spacing: -.018em;
}

.research-summary-meta {
    color: #657168;
    font-size: 12px;
}

.research-status {
    min-height: 30px;
    padding: 6px 12px;
    border: 1px solid rgba(8, 117, 44, .10);
    font-size: 10px;
}

.research-status-overview {
    margin-bottom: 16px;
    padding: 16px 18px;
    border-radius: 18px;
    box-shadow: 0 10px 24px rgba(21, 55, 28, .035);
}

.research-status-overview-icon,
.research-panel-icon,
.panel-title-icon {
    border-radius: 12px;
}

.research-status-overview h3 {
    font-size: 15px;
}

.research-status-overview p {
    font-size: 11px;
    line-height: 1.55;
}

.research-grid {
    grid-template-columns: minmax(0, 1.55fr) minmax(280px, .72fr);
    gap: 16px;
}

.research-column {
    gap: 16px;
}

/* Faculty and RPS research details: redundant summary boxes are hidden.
   This is presentation-only; workflow logic is untouched. */
.research-grid.research-clean-grid {
    grid-template-columns: minmax(0, 1fr);
}

.research-panel,
.workflow-card,
.research-document-viewer {
    border: 1px solid #e3ebe3;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 12px 30px rgba(21, 55, 28, .045);
}

.research-panel-header {
    min-height: 56px;
    padding: 14px 18px;
    background: linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
}

.research-panel-title {
    font-size: 14px;
    letter-spacing: -.01em;
}

.research-panel-body {
    padding: 16px 18px;
}

.info-grid {
    gap: 14px 18px;
}

.info-item label {
    margin-bottom: 4px;
    color: #738078;
    font-size: 10px;
    letter-spacing: .055em;
}

.info-item strong {
    font-size: 12px;
    line-height: 1.45;
}

.content-block {
    padding: 13px 0;
}

.content-block h4 {
    font-size: 12px;
}

.content-block p {
    font-size: 12px;
    line-height: 1.65;
}


/* =========================================================
   PRIVATE RPS SECTION COMMENTS
   Compact display: comment text stays hidden until opened.
========================================================= */

.section-comment-control {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 5px;
}

.section-comment-button {
    min-height: 24px;
    padding: 2px 0;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 0;
    background: transparent;
    color: #68766c;
    font-size: 9.5px;
    font-weight: 700;
    line-height: 1.2;
    text-decoration: none;
    transition: .16s ease;
}

.section-comment-button i {
    color: var(--asu-green);
    font-size: 11px;
}

.section-comment-button:hover,
.section-comment-button:focus {
    color: var(--asu-green);
    background: transparent;
    box-shadow: none;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.section-comment-button.has-saved-comment,
.section-comment-button.has-pending-comment {
    border: 0;
    background: transparent;
}

.section-comment-button.has-pending-comment {
    color: #796400;
}

.section-comment-state {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #8a7100;
    font-size: 8.5px;
    font-weight: 700;
}

.section-comment-state.is-pending {
    color: #8a7100;
}

.section-comment-state.is-saved {
    color: #007a33;
}

.section-comment-lock {
    display: inline-flex;
    align-items: center;
    color: #9aa39c;
    font-size: 8.5px;
}

.section-comment-readonly-control {
    margin-top: 4px;
}

.section-comment-modal-note {
    margin-top: 8px;
    color: #7a847c;
    font-size: 10px;
    line-height: 1.5;
}

.section-comment-modal-note i {
    margin-right: 4px;
    color: var(--asu-green);
}

#rpsSectionCommentModal .modal-content,
#sectionRemarkViewModal .modal-content {
    border: 0;
    border-radius: 15px;
    box-shadow: 0 22px 60px rgba(20, 42, 27, .18);
}

#rpsSectionCommentModal .modal-header,
#rpsSectionCommentModal .modal-footer,
#sectionRemarkViewModal .modal-header,
#sectionRemarkViewModal .modal-footer {
    border-color: #edf1ed;
}

#rpsSectionCommentModal .modal-title,
#sectionRemarkViewModal .modal-title {
    font-size: 15px;
    font-weight: 800;
    color: #1d2c22;
}

#rpsSectionCommentText {
    min-height: 115px;
    resize: vertical;
    font-size: 11px;
    line-height: 1.55;
}

#rpsSectionCommentText:focus {
    border-color: var(--asu-green);
    box-shadow: 0 0 0 .16rem rgba(8, 117, 44, .10);
}

.section-comment-view-message {
    margin: 0;
    padding: 12px 13px;
    border-radius: 10px;
    background: #f8faf8;
    color: #465149;
    font-size: 11px;
    line-height: 1.65;
    white-space: pre-wrap;
}

.section-comment-view-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
    color: #808a82;
    font-size: 9px;
}

.section-comment-view-meta span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.document-item {
    padding: 11px 12px;
    border-radius: 13px;
    background: #fbfdfb;
}

.document-icon,
.document-open-icon {
    border-radius: 11px;
}

.document-info strong {
    font-size: 12px;
}

.document-info small {
    font-size: 10px;
}

.timeline-item,
.evaluation-item {
    padding: 10px 0;
}

.timeline-item strong,
.evaluation-item strong {
    font-size: 12px;
}

.timeline-item small,
.evaluation-item small,
.timeline-item p,
.evaluation-item p {
    font-size: 10.5px;
}

.status-history-panel {
    min-height: 520px;
    height: 520px;
}

.workflow-card {
    margin-top: 16px;
    padding: 16px 18px;
    border-left: 4px solid #08752c;
}

.workflow-content {
    gap: 16px;
}

.workflow-text h4 {
    font-size: 15px;
    letter-spacing: -.01em;
}

.workflow-text p {
    max-width: 740px;
    font-size: 12px;
    line-height: 1.55;
}

.workflow-notice {
    margin-top: 10px;
    padding: 10px 12px;
    border-left-color: #08752c;
    background: #f2faf4;
    color: #415247;
    font-size: 11px;
}

.admin-review-form .form-label {
    margin-bottom: 6px;
    color: #435047;
    font-size: 12px;
    font-weight: 700;
}

.admin-review-form .form-select,
.admin-review-form textarea,
.admin-review-form .form-control {
    border-radius: 12px;
    border-color: #dbe6dd;
    font-size: 13px;
}

.admin-review-form textarea {
    min-height: 96px;
}

.admin-review-actions {
    gap: 9px;
    margin-top: 14px;
}

.admin-review-actions .btn,
.workflow-buttons .btn,
.research-status-overview .btn {
    border-radius: 12px;
    padding: 9px 14px;
    font-size: 12px;
    font-weight: 700;
}

.empty-data {
    padding: 18px 10px;
    font-size: 12px;
}

@media (max-width: 1000px) {
    .research-grid {
        grid-template-columns: 1fr;
    }

    .status-history-panel {
        min-height: 430px;
        height: 430px;
    }
}



/* =========================================================
   FINAL TOP STATUS BAR FIX
   - Restores the green university-style header card
   - Shows queue/stage while under review
   - Shows actual percentage during implementation
========================================================= */

.research-summary {
    position: relative;
    overflow: hidden;
    margin-bottom: 18px;
    padding: 24px 26px;
    border: 0;
    border-left: 0;
    border-radius: 18px;
    background:
        radial-gradient(circle at 94% 0%, rgba(255, 210, 31, .24), transparent 28%),
        linear-gradient(135deg, #08752c 0%, #045f24 58%, #04491d 100%);
    color: #ffffff;
    box-shadow: 0 16px 36px rgba(6, 74, 28, .18);
}

.research-summary::after {
    content: "";
    position: absolute;
    right: -55px;
    top: -55px;
    width: 190px;
    height: 190px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .10);
    pointer-events: none;
}

.research-summary h2 {
    color: #ffffff;
    font-size: 22px;
    letter-spacing: -.025em;
}

.research-summary-meta {
    color: rgba(255, 255, 255, .82);
    font-size: 12px;
}

.research-summary-meta i {
    color: #ffd21f;
}

.research-summary .research-status {
    position: relative;
    z-index: 1;
    border: 1px solid rgba(255, 255, 255, .30);
    background: rgba(255, 255, 255, .92);
    color: #045f24;
    box-shadow: 0 8px 20px rgba(0, 0, 0, .10);
}

.workflow-status-strip {
    position: relative;
    z-index: 1;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, .18);
}

.workflow-status-strip .research-progress-head span {
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .02em;
    text-transform: uppercase;
}

.workflow-status-strip .research-progress-subtitle {
    display: block;
    max-width: 760px;
    margin-top: 4px;
    color: rgba(255, 255, 255, .76);
    font-size: 11px;
    line-height: 1.5;
}

.workflow-status-strip .research-progress-head strong {
    min-width: 78px;
    padding: 8px 12px;
    border-radius: 999px;
    background: rgba(255, 210, 31, .95);
    color: #06451c;
    text-align: center;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.workflow-status-strip.is-step-mode .research-progress-head strong {
    max-width: 230px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.research-summary .research-progress-bar {
    height: 8px;
    margin-top: 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .22);
}

.research-summary .research-progress-value {
    height: 100%;
    border-radius: inherit;
    background: #ffd21f;
}

@media (max-width: 700px) {
    .research-summary {
        padding: 20px;
    }

    .workflow-status-strip .research-progress-head strong {
        align-self: flex-start;
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

.asu-sidebar {
    background:
        linear-gradient(180deg, #007A34 0%, #006B2E 52%, #004F22 100%) !important;
    box-shadow: 12px 0 32px rgba(0, 63, 26, .14) !important;
}

.sidebar-brand {
    border-bottom-color: rgba(255, 255, 255, .14) !important;
}

.brand-logo,
.user-avatar {
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
}

.sidebar-link {
    color: rgba(255, 255, 255, .92) !important;
}

.sidebar-link:hover {
    background: rgba(255, 255, 255, .11) !important;
    color: #FFFFFF !important;
}

.sidebar-link.active {
    background: var(--asu-yellow) !important;
    color: var(--asu-black) !important;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .12) !important;
}

.notification-count,
.sidebar-notification-badge {
    background: var(--asu-orange) !important;
    color: #FFFFFF !important;
    border: 2px solid rgba(255, 255, 255, .35) !important;
    box-shadow: 0 8px 18px rgba(242, 140, 40, .32) !important;
}

.sidebar-link.active .notification-count,
.sidebar-link.active .sidebar-notification-badge {
    background: var(--asu-orange) !important;
    color: #FFFFFF !important;
}

.asu-topbar {
    background: #FFFFFF !important;
    border-bottom: 1px solid rgba(0, 107, 46, .12) !important;
    box-shadow: 0 8px 24px rgba(0, 79, 34, .05) !important;
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



.workflow-inline-link {
    color: #08752c;
    font-weight: 700;
    text-decoration: none;
}

.workflow-inline-link:hover {
    color: #065f24;
    text-decoration: underline;
}

.admin-review-form .research-field-note {
    display: block;
    margin-top: 6px;
    color: #768176;
    font-size: 10px;
    line-height: 1.45;
}


/* =========================================================
   SYSTEM-GENERATED CERTIFICATE
========================================================= */

.generated-certificate-page {
    max-width: 980px;
    margin: 0 auto;
}

.generated-certificate-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}

.generated-certificate-toolbar h1 {
    margin: 0 0 4px;
    color: #102416;
    font-size: 22px;
    font-weight: 800;
}

.generated-certificate-toolbar p {
    margin: 0;
    color: #677168;
    font-size: 11px;
}

.generated-certificate-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.generated-certificate-sheet {
    position: relative;
    min-height: 980px;
    padding: 54px 58px;
    overflow: hidden;
    border: 1px solid #dbe6da;
    border-radius: 18px;
    background: #ffffff;
    color: #111111;
    box-shadow: 0 16px 38px rgba(16, 57, 28, .08);
}

.generated-certificate-sheet::before {
    content: "";
    position: absolute;
    inset: 18px;
    border: 2px solid #12612d;
    border-radius: 12px;
    pointer-events: none;
}

.generated-certificate-sheet::after {
    content: "ASU";
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%) rotate(-18deg);
    color: rgba(18, 97, 45, .045);
    font-size: 150px;
    font-weight: 900;
    letter-spacing: .04em;
    pointer-events: none;
}

.generated-certificate-inner {
    position: relative;
    z-index: 2;
}

.generated-certificate-header {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr) 72px;
    align-items: center;
    gap: 16px;
    padding-bottom: 18px;
    border-bottom: 4px double #12612d;
    text-align: center;
}

.generated-certificate-seal {
    width: 72px;
    height: 72px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #12612d;
    border-radius: 50%;
    background: #fff8d7;
    color: #12612d;
    font-size: 31px;
}

.generated-certificate-header small {
    display: block;
    color: #4f5d52;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.generated-certificate-header h2 {
    margin: 4px 0;
    color: #12612d;
    font-size: 23px;
    font-weight: 900;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.generated-certificate-header p {
    margin: 0;
    color: #5d665f;
    font-size: 11px;
    line-height: 1.45;
}

.generated-certificate-title {
    margin: 44px 0 26px;
    text-align: center;
}

.generated-certificate-title span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 18px;
    border-radius: 999px;
    background: #fff4c8;
    color: #12612d;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.generated-certificate-title h3 {
    margin: 16px 0 0;
    color: #111111;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 35px;
    font-weight: 700;
    letter-spacing: .02em;
}

.generated-certificate-body {
    color: #202820;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 16px;
    line-height: 2;
    text-align: justify;
}

.generated-certificate-highlight {
    color: #12612d;
    font-weight: 800;
}

.generated-certificate-research-title {
    margin: 22px 0;
    padding: 18px 22px;
    border-left: 6px solid #ffd449;
    border-radius: 10px;
    background: #f8fbf8;
    color: #102416;
    font-family: Arial, sans-serif;
    font-size: 17px;
    font-weight: 800;
    line-height: 1.55;
    text-align: left;
}

.generated-certificate-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 18px;
    margin: 30px 0 34px;
    padding: 18px;
    border: 1px solid #dfe8dd;
    border-radius: 14px;
    background: #fbfcfb;
    font-family: Arial, sans-serif;
}

.generated-certificate-detail small {
    display: block;
    margin-bottom: 3px;
    color: #687369;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.generated-certificate-detail strong {
    display: block;
    color: #111111;
    font-size: 12px;
    line-height: 1.45;
}

.generated-certificate-remarks {
    margin-top: 18px;
    padding: 14px 16px;
    border-radius: 12px;
    background: #fffaf0;
    color: #5f4b13;
    font-family: Arial, sans-serif;
    font-size: 12px;
    line-height: 1.65;
}

.generated-certificate-footer {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 40px;
    margin-top: 70px;
    font-family: Arial, sans-serif;
}

.generated-certificate-signature {
    text-align: center;
}

.generated-certificate-line {
    height: 1px;
    margin-bottom: 9px;
    background: #111111;
}

.generated-certificate-signature strong {
    display: block;
    color: #111111;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.generated-certificate-signature span {
    display: block;
    margin-top: 2px;
    color: #5f675f;
    font-size: 10px;
}

.generated-certificate-note {
    margin-top: 40px;
    padding-top: 14px;
    border-top: 1px solid #e5ece4;
    color: #687369;
    font-family: Arial, sans-serif;
    font-size: 10px;
    line-height: 1.6;
    text-align: center;
}


.generated-certificate-body p {
    margin: 0 0 17px;
}

.certificate-main-heading {
    margin: 0;
    color: #111111;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: .05em;
    line-height: 1.35;
    text-align: center;
    text-transform: uppercase;
}

.certificate-subtitle-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    padding: 7px 16px;
    border-radius: 999px;
    background: #fff4c8;
    color: #12612d;
    font-family: Arial, sans-serif;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.certificate-quoted-title {
    margin: 18px 0 23px;
    padding: 20px 24px;
    border-left: 6px solid #ffd449;
    border-radius: 10px;
    background: #f8fbf8;
    color: #102416;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.55;
    text-align: center;
}

.certificate-classification-box {
    margin: 18px auto 20px;
    max-width: 520px;
    padding: 18px 22px;
    border: 2px solid #12612d;
    border-radius: 14px;
    background: #fbfff9;
    color: #12612d;
    font-family: Arial, sans-serif;
    font-size: 24px;
    font-weight: 900;
    letter-spacing: .08em;
    text-align: center;
    text-transform: uppercase;
}

.certificate-description-box {
    margin: 0 0 24px;
    padding: 15px 18px;
    border-radius: 12px;
    background: #fffaf0;
    color: #534515;
    font-family: Arial, sans-serif;
    font-size: 12px;
    line-height: 1.7;
    text-align: left;
}

.certificate-info-lines {
    margin: 28px 0 24px;
    padding: 18px 20px;
    border: 1px solid #dfe8dd;
    border-radius: 14px;
    background: #fbfcfb;
    font-family: Arial, sans-serif;
}

.certificate-info-line {
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr);
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px dashed #d9e5d8;
    color: #202820;
    font-size: 12px;
    line-height: 1.45;
}

.certificate-info-line:last-child {
    border-bottom: 0;
}

.certificate-info-line span:first-child {
    color: #687369;
    font-weight: 900;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.certificate-info-line span:last-child {
    font-weight: 700;
}

.certificate-system-note {
    margin-top: 26px;
    padding: 15px 18px;
    border-top: 1px solid #dfe8dd;
    border-bottom: 1px solid #dfe8dd;
    color: #4c554d;
    font-family: Arial, sans-serif;
    font-size: 11px;
    line-height: 1.7;
    text-align: center;
}

.certificate-generated-lines {
    margin-top: 18px;
    color: #202820;
    font-family: Arial, sans-serif;
    font-size: 12px;
    line-height: 1.8;
}

@media (max-width: 768px) {
    .certificate-main-heading {
        font-size: 20px;
    }

    .certificate-quoted-title {
        font-size: 16px;
    }

    .certificate-classification-box {
        font-size: 18px;
    }

    .certificate-info-line {
        grid-template-columns: 1fr;
        gap: 3px;
    }
}

@media print {
    body {
        background: #ffffff !important;
    }

    .asu-sidebar,
    .asu-topbar,
    .generated-certificate-toolbar,
    .content-footer {
        display: none !important;
    }

    .main-page,
    .page-content,
    .research-view,
    .generated-certificate-page {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .generated-certificate-sheet {
        min-height: auto;
        padding: 45px 48px;
        border: none;
        border-radius: 0;
        box-shadow: none;
        page-break-inside: avoid;
    }
}



/* Professional NTP modal */
.modal-kicker {
    font-size: 11px;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.ntp-modal-summary {
    border: 1px solid #e5eee8;
    border-left: 4px solid #0b6b2a;
    border-radius: 14px;
    background: #f8fbf8;
    padding: 13px 15px;
    color: #223127;
}

.ntp-modal-summary span {
    color: #59665e;
    font-size: 13px;
}



/* =========================================================
   PROFESSIONAL NTP POPUP - NO BOOTSTRAP DEPENDENCY
========================================================= */

body.ntp-popup-open {
    overflow: hidden !important;
}

.ntp-confirm-overlay {
    position: fixed !important;
    inset: 0 !important;
    z-index: 2147483000 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 24px !important;
    background: rgba(5, 28, 15, .58) !important;
    backdrop-filter: blur(3px);
}

.ntp-confirm-box {
    position: relative !important;
    z-index: 2147483001 !important;
    width: min(520px, calc(100vw - 32px)) !important;
    max-width: 520px !important;
    padding: 32px 30px 26px !important;
    border-radius: 24px !important;
    border: 1px solid rgba(18, 97, 45, .16) !important;
    background: #ffffff !important;
    color: #13251a !important;
    box-shadow: 0 28px 80px rgba(0, 0, 0, .28) !important;
    text-align: center !important;
    opacity: 1 !important;
    visibility: visible !important;
    transform: none !important;
}

.ntp-confirm-icon {
    width: 68px !important;
    height: 68px !important;
    margin: 0 auto 14px !important;
    border-radius: 22px !important;
    background: #0b6b2a !important;
    color: #ffd449 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 30px !important;
}

.ntp-confirm-kicker {
    margin: 0 0 6px !important;
    color: #0b6b2a !important;
    font-size: 11px !important;
    font-weight: 900 !important;
    letter-spacing: .12em !important;
    text-transform: uppercase !important;
}

.ntp-confirm-title {
    margin: 0 0 10px !important;
    color: #122419 !important;
    font-size: 22px !important;
    font-weight: 900 !important;
}

.ntp-confirm-message {
    max-width: 420px !important;
    margin: 0 auto 18px !important;
    color: #5f6e65 !important;
    font-size: 14px !important;
    line-height: 1.55 !important;
}

.ntp-confirm-summary {
    margin: 0 0 22px !important;
    padding: 15px 16px !important;
    border: 1px solid #e1eadf !important;
    border-radius: 16px !important;
    background: #f7faf7 !important;
    text-align: left !important;
}

.ntp-confirm-summary strong {
    display: block !important;
    color: #0b6b2a !important;
    font-size: 13px !important;
    line-height: 1.35 !important;
}

.ntp-confirm-summary span {
    display: block !important;
    margin-top: 4px !important;
    color: #4b5c51 !important;
    font-size: 13px !important;
    line-height: 1.45 !important;
}

.ntp-confirm-actions {
    display: flex !important;
    justify-content: flex-end !important;
    gap: 10px !important;
}

.ntp-confirm-actions .btn {
    min-width: 116px !important;
}

@media (max-width: 576px) {
    .ntp-confirm-box {
        padding: 28px 20px 22px !important;
    }

    .ntp-confirm-actions {
        flex-direction: column-reverse !important;
    }

    .ntp-confirm-actions .btn {
        width: 100% !important;
    }
}


/* NTP action buttons */
.status-action-buttons {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.certificate-next-step-note {
    background: #f8fbf8;
    border-color: #b9d7c2;
    color: #0f5132;
}

@media (max-width: 768px) {
    .status-action-buttons {
        width: 100%;
        justify-content: stretch;
    }

    .status-action-buttons .btn {
        width: 100%;
    }
}

@media print {
    .generated-certificate-toolbar,
    .asu-sidebar,
    .asu-topbar,
    .content-footer {
        display: none !important;
    }

    .main-page,
    .page-content,
    .research-view,
    .generated-certificate-page {
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
    }

    .generated-certificate-sheet {
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
    }
}



/* =========================================================
   NOTICE TO PROCEED BUTTON FIX
   Professional same-line action buttons
========================================================= */

.workflow-card .ntp-action-row {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 10px !important;
    flex-wrap: nowrap !important;
    margin-top: 0 !important;
    min-width: 360px;
}

.workflow-card .ntp-action-row .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    min-width: 178px;
    height: 42px;
    padding: 0 16px !important;
    border-radius: 14px !important;
    white-space: nowrap !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
}

.workflow-card .ntp-generated-actions .btn {
    min-width: 190px;
}

.workflow-card .ntp-ready-actions .btn-asu {
    min-width: 230px;
}

@media (max-width: 1100px) {
    .workflow-content {
        align-items: flex-start !important;
        flex-direction: column !important;
    }

    .workflow-card .ntp-action-row {
        justify-content: flex-start !important;
        width: 100% !important;
        min-width: 0 !important;
    }
}

@media (max-width: 620px) {
    .workflow-card .ntp-action-row {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .workflow-card .ntp-action-row .btn {
        width: 100% !important;
        min-width: 0 !important;
    }
}



/* =========================================================
   STEP 1 — REMS MODAL / POP-UP UI POLISH
   UI ONLY. Workflow, actions, button values and form logic
   are intentionally untouched.
========================================================= */

/* Keep modal typography consistent with the rest of REMS. */
#submitModal,
#resubmitModal,
#rpsSectionCommentModal,
#sectionRemarkViewModal {
    --rems-modal-green: #007a33;
    --rems-modal-green-dark: #005c26;
    --rems-modal-text: #172019;
    --rems-modal-muted: #6f7a72;
    --rems-modal-border: #e1e9e3;
    --rems-modal-soft: #f7faf8;
    font-family: "Century Gothic", Arial, sans-serif !important;
}

/* Softer backdrop — no harsh browser-popup feel. */
.modal-backdrop.show {
    opacity: .42 !important;
}

/* Consistent widths. */
#submitModal .modal-dialog,
#resubmitModal .modal-dialog,
#rpsSectionCommentModal .modal-dialog {
    width: min(520px, calc(100vw - 28px)) !important;
    max-width: 520px !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

#sectionRemarkViewModal .modal-dialog {
    width: min(460px, calc(100vw - 28px)) !important;
    max-width: 460px !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

/* Clean white card with one restrained green accent. */
#submitModal .modal-content,
#resubmitModal .modal-content,
#rpsSectionCommentModal .modal-content,
#sectionRemarkViewModal .modal-content {
    position: relative !important;
    overflow: hidden !important;

    border: 1px solid var(--rems-modal-border) !important;
    border-radius: 18px !important;

    background: #ffffff !important;

    box-shadow:
        0 24px 68px rgba(22, 45, 28, .18) !important;
}

#submitModal .modal-content::before,
#resubmitModal .modal-content::before,
#rpsSectionCommentModal .modal-content::before,
#sectionRemarkViewModal .modal-content::before {
    content: "" !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    height: 4px !important;
    background: linear-gradient(
        90deg,
        var(--rems-modal-green-dark),
        var(--rems-modal-green)
    ) !important;
}

/* Header */
#submitModal .modal-header,
#resubmitModal .modal-header,
#rpsSectionCommentModal .modal-header,
#sectionRemarkViewModal .modal-header {
    min-height: 0 !important;
    padding: 21px 22px 10px !important;

    align-items: flex-start !important;

    border: 0 !important;
    background: #ffffff !important;
}

#submitModal .modal-title,
#resubmitModal .modal-title,
#rpsSectionCommentModal .modal-title,
#sectionRemarkViewModal .modal-title {
    margin: 0 !important;

    color: var(--rems-modal-text) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 17px !important;
    font-weight: 800 !important;
    line-height: 1.3 !important;
    letter-spacing: -.015em !important;
}

/* Small institutional label — Century Gothic only. */
#rpsSectionCommentModal .modal-kicker,
#sectionRemarkViewModal .modal-kicker {
    margin-bottom: 5px !important;

    color: var(--rems-modal-green) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    letter-spacing: .07em !important;
    text-transform: uppercase !important;
}

/* Close button: simple and quiet. */
#submitModal .btn-close,
#resubmitModal .btn-close,
#rpsSectionCommentModal .btn-close,
#sectionRemarkViewModal .btn-close {
    width: 30px !important;
    height: 30px !important;

    margin: -3px -4px 0 12px !important;
    padding: 7px !important;

    border-radius: 9px !important;

    opacity: .48 !important;

    box-shadow: none !important;
}

#submitModal .btn-close:hover,
#resubmitModal .btn-close:hover,
#rpsSectionCommentModal .btn-close:hover,
#sectionRemarkViewModal .btn-close:hover {
    background-color: #f3f6f4 !important;
    opacity: .78 !important;
}

/* Body */
#submitModal .modal-body,
#resubmitModal .modal-body,
#rpsSectionCommentModal .modal-body,
#sectionRemarkViewModal .modal-body {
    padding: 10px 22px 18px !important;

    color: #47534b !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 11px !important;
    line-height: 1.65 !important;
}

#submitModal .modal-body > p,
#resubmitModal .modal-body > p {
    margin: 0 0 13px !important;

    color: #3f4a43 !important;

    font-size: 11.5px !important;
    font-weight: 500 !important;
    line-height: 1.65 !important;
}

/*
 * Replace the old yellow warning block with a minimal neutral note.
 * Same message, same form, same submission behavior.
 */
#submitModal .alert-warning,
#resubmitModal .alert-warning {
    margin: 0 !important;
    padding: 11px 12px !important;

    border: 1px solid #dfe8e1 !important;
    border-left: 3px solid #8ab697 !important;
    border-radius: 10px !important;

    background: #f7faf8 !important;
    color: #667169 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    font-weight: 400 !important;
    line-height: 1.55 !important;
}

/* Footer */
#submitModal .modal-footer,
#resubmitModal .modal-footer,
#rpsSectionCommentModal .modal-footer,
#sectionRemarkViewModal .modal-footer {
    padding: 0 22px 20px !important;

    justify-content: flex-end !important;
    gap: 8px !important;

    border: 0 !important;
    background: #ffffff !important;
}

#submitModal .modal-footer .btn,
#resubmitModal .modal-footer .btn,
#rpsSectionCommentModal .modal-footer .btn,
#sectionRemarkViewModal .modal-footer .btn {
    width: auto !important;
    min-width: 94px !important;
    min-height: 40px !important;

    margin: 0 !important;
    padding: 0 13px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;

    border-radius: 10px !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
    line-height: 1 !important;

    box-shadow: none !important;
}

/* Secondary button */
#submitModal .btn-outline-secondary,
#resubmitModal .btn-outline-secondary,
#rpsSectionCommentModal .btn-outline-secondary,
#sectionRemarkViewModal .btn-outline-secondary {
    border: 1px solid #d6dfd8 !important;
    background: #ffffff !important;
    color: #566159 !important;
}

#submitModal .btn-outline-secondary:hover,
#resubmitModal .btn-outline-secondary:hover,
#rpsSectionCommentModal .btn-outline-secondary:hover,
#sectionRemarkViewModal .btn-outline-secondary:hover {
    border-color: #c4d1c7 !important;
    background: #f6f8f6 !important;
    color: #263129 !important;
}

/* Primary action */
#submitModal .btn-asu,
#resubmitModal .btn-asu,
#rpsSectionCommentModal .btn-asu {
    border: 1px solid var(--rems-modal-green) !important;
    background: var(--rems-modal-green) !important;
    color: #ffffff !important;
}

#submitModal .btn-asu:hover,
#resubmitModal .btn-asu:hover,
#rpsSectionCommentModal .btn-asu:hover {
    border-color: var(--rems-modal-green-dark) !important;
    background: var(--rems-modal-green-dark) !important;
}

/* RPS comment editor */
#rpsSectionCommentText {
    min-height: 120px !important;
    padding: 11px 12px !important;

    resize: vertical !important;

    border: 1px solid #d9e2dc !important;
    border-radius: 10px !important;

    background: #fbfcfb !important;
    color: #273229 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    line-height: 1.6 !important;

    box-shadow: none !important;
}

#rpsSectionCommentText:focus {
    border-color: #7caf89 !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(0, 122, 51, .07) !important;
}

#rpsSectionCommentText::placeholder {
    color: #9aa39d !important;
}

/* Comment helper note */
#rpsSectionCommentModal .section-comment-modal-note {
    margin-top: 10px !important;
    padding: 10px 11px !important;

    border: 1px solid #e5ebe6 !important;
    border-radius: 10px !important;

    background: #f8faf8 !important;
    color: #707b73 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9px !important;
    line-height: 1.55 !important;
}

#rpsSectionCommentModal .section-comment-modal-note i {
    margin-right: 4px !important;
    color: var(--rems-modal-green) !important;
}

/* Read-only comment popup */
#sectionRemarkViewModal .section-comment-view-message {
    margin: 0 !important;
    padding: 12px 13px !important;

    border: 1px solid #e4ebe6 !important;
    border-radius: 10px !important;

    background: #f8faf8 !important;
    color: #3f4a43 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    line-height: 1.7 !important;
}

#sectionRemarkViewModal .section-comment-view-meta {
    margin-top: 10px !important;

    gap: 10px !important;

    color: #7b867e !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
}

/* Cleaner modal entrance */
#submitModal.fade .modal-dialog,
#resubmitModal.fade .modal-dialog,
#rpsSectionCommentModal.fade .modal-dialog,
#sectionRemarkViewModal.fade .modal-dialog {
    transform: translateY(8px) scale(.99) !important;
    transition:
        transform 140ms ease,
        opacity 140ms ease !important;
}

#submitModal.show .modal-dialog,
#resubmitModal.show .modal-dialog,
#rpsSectionCommentModal.show .modal-dialog,
#sectionRemarkViewModal.show .modal-dialog {
    transform: translateY(0) scale(1) !important;
}

/* Mobile */
@media (max-width: 576px) {

    #submitModal .modal-dialog,
    #resubmitModal .modal-dialog,
    #rpsSectionCommentModal .modal-dialog,
    #sectionRemarkViewModal .modal-dialog {
        width: calc(100vw - 20px) !important;
        margin: 10px auto !important;
    }

    #submitModal .modal-header,
    #resubmitModal .modal-header,
    #rpsSectionCommentModal .modal-header,
    #sectionRemarkViewModal .modal-header {
        padding: 19px 17px 9px !important;
    }

    #submitModal .modal-body,
    #resubmitModal .modal-body,
    #rpsSectionCommentModal .modal-body,
    #sectionRemarkViewModal .modal-body {
        padding: 9px 17px 16px !important;
    }

    #submitModal .modal-footer,
    #resubmitModal .modal-footer,
    #rpsSectionCommentModal .modal-footer,
    #sectionRemarkViewModal .modal-footer {
        padding: 0 17px 17px !important;
    }

    #submitModal .modal-footer .btn,
    #resubmitModal .modal-footer .btn,
    #rpsSectionCommentModal .modal-footer .btn,
    #sectionRemarkViewModal .modal-footer .btn {
        min-width: 0 !important;
        flex: 1 1 0 !important;
    }
}



/* =========================================================
   STEP 1B — PLAIN MODAL REFINEMENT
   Removes the top green strip and close X.
   Keeps only a subtle green accent on the LEFT side.
   UI ONLY — no workflow or button logic changes.
========================================================= */

#submitModal .modal-content,
#resubmitModal .modal-content,
#rpsSectionCommentModal .modal-content,
#sectionRemarkViewModal .modal-content {
    overflow: hidden !important;

    border: 1px solid #e1e8e3 !important;
    border-left: 4px solid #007a33 !important;
    border-radius: 16px !important;

    background: #ffffff !important;

    box-shadow:
        0 22px 56px rgba(25, 38, 29, .16) !important;
}

/* Remove the decorative green strip at the top */
#submitModal .modal-content::before,
#resubmitModal .modal-content::before,
#rpsSectionCommentModal .modal-content::before,
#sectionRemarkViewModal .modal-content::before {
    display: none !important;
    content: none !important;
}

/* Remove the X close icon for a cleaner confirmation modal */
#submitModal .btn-close,
#resubmitModal .btn-close,
#rpsSectionCommentModal .btn-close,
#sectionRemarkViewModal .btn-close {
    display: none !important;
}

/* Simpler header */
#submitModal .modal-header,
#resubmitModal .modal-header,
#rpsSectionCommentModal .modal-header,
#sectionRemarkViewModal .modal-header {
    padding: 22px 22px 8px !important;

    border: 0 !important;
    background: #ffffff !important;
}

/* Plain title */
#submitModal .modal-title,
#resubmitModal .modal-title,
#rpsSectionCommentModal .modal-title,
#sectionRemarkViewModal .modal-title {
    color: #1c251f !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 17px !important;
    font-weight: 800 !important;
    line-height: 1.3 !important;
}

/* Cleaner body with no extra visual noise */
#submitModal .modal-body,
#resubmitModal .modal-body,
#rpsSectionCommentModal .modal-body,
#sectionRemarkViewModal .modal-body {
    padding: 10px 22px 18px !important;
    background: #ffffff !important;
}

/* Keep the note very minimal */
#submitModal .alert-warning,
#resubmitModal .alert-warning {
    margin-top: 12px !important;
    padding: 10px 11px !important;

    border: 1px solid #e1e8e3 !important;
    border-radius: 9px !important;

    background: #f8faf8 !important;
    color: #6b756e !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.3px !important;
    font-weight: 400 !important;
    line-height: 1.5 !important;
}

/* No colored bar inside the note */
#submitModal .alert-warning,
#resubmitModal .alert-warning {
    border-left: 1px solid #e1e8e3 !important;
}

/* Footer stays plain */
#submitModal .modal-footer,
#resubmitModal .modal-footer,
#rpsSectionCommentModal .modal-footer,
#sectionRemarkViewModal .modal-footer {
    padding: 0 22px 20px !important;

    border: 0 !important;
    background: #ffffff !important;
}

/* Slightly softer primary button */
#submitModal .btn-asu,
#resubmitModal .btn-asu,
#rpsSectionCommentModal .btn-asu {
    background: #007a33 !important;
    border-color: #007a33 !important;
    color: #ffffff !important;

    box-shadow: none !important;
}

/* Cancel remains clean white */
#submitModal .btn-outline-secondary,
#resubmitModal .btn-outline-secondary,
#rpsSectionCommentModal .btn-outline-secondary,
#sectionRemarkViewModal .btn-outline-secondary {
    background: #ffffff !important;
    border-color: #d7e0d9 !important;
    color: #566159 !important;
}

/* Faster, subtle entrance */
#submitModal.fade .modal-dialog,
#resubmitModal.fade .modal-dialog,
#rpsSectionCommentModal.fade .modal-dialog,
#sectionRemarkViewModal.fade .modal-dialog {
    transform: translateY(5px) !important;

    transition:
        transform 120ms ease,
        opacity 120ms ease !important;
}

#submitModal.show .modal-dialog,
#resubmitModal.show .modal-dialog,
#rpsSectionCommentModal.show .modal-dialog,
#sectionRemarkViewModal.show .modal-dialog {
    transform: translateY(0) !important;
}



/* =========================================================
   STEP 2 — MODERN PORTRAIT UREC / IACUC CERTIFICATE
   Inspired by a clean modern portrait certificate layout.
   ASU green + gold, words only, Poppins + Century Gothic.
   UI ONLY — no workflow/action changes.
========================================================= */

.urec-modern-page {
    width: 100% !important;
    max-width: 960px !important;
    margin: 0 auto !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
}


/* =========================================================
   TOOLBAR
========================================================= */

.urec-modern-page .generated-certificate-toolbar {
    margin: 0 0 18px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 16px !important;
}

.urec-modern-page .generated-certificate-toolbar h1 {
    margin: 0 0 4px !important;

    color: #18221b !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 22px !important;
    font-weight: 800 !important;
    line-height: 1.25 !important;
    letter-spacing: -.02em !important;
}

.urec-modern-page .generated-certificate-toolbar p {
    margin: 0 !important;

    color: #748078 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    line-height: 1.55 !important;
}

.urec-modern-page .generated-certificate-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
}

.urec-modern-page .generated-certificate-actions .btn {
    width: auto !important;
    min-height: 40px !important;

    padding: 0 13px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;

    border-radius: 10px !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
}


/* =========================================================
   PORTRAIT CERTIFICATE SHEET
========================================================= */

.urec-modern-sheet {
    position: relative !important;

    width: min(100%, 790px) !important;
    aspect-ratio: 210 / 297 !important;
    min-height: 1080px !important;

    margin: 0 auto !important;
    padding: 30px !important;

    overflow: hidden !important;

    border: 1px solid #d8ddd9 !important;
    border-radius: 8px !important;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fffefa 100%
        ) !important;

    box-shadow:
        0 18px 46px rgba(20, 37, 25, .10) !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
}


/* subtle paper texture */
.urec-modern-sheet {
    background-image:
        radial-gradient(
            circle at 18% 25%,
            rgba(0, 122, 51, .018) 0,
            rgba(0, 122, 51, .018) 1px,
            transparent 1px
        ),
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fffefa 100%
        ) !important;

    background-size:
        13px 13px,
        auto !important;
}


/* =========================================================
   DECORATIVE CORNER CURVES
   No logos/seals — design only.
========================================================= */

.urec-modern-top-shape,
.urec-modern-bottom-shape {
    position: absolute !important;

    left: 0 !important;
    right: 0 !important;

    z-index: 0 !important;

    pointer-events: none !important;
}

.urec-modern-top-shape {
    top: 0 !important;
    height: 205px !important;

    background:
        linear-gradient(
            132deg,
            #004f24 0%,
            #007a33 58%,
            #1c8e3f 100%
        ) !important;

    clip-path:
        polygon(
            38% 0,
            100% 0,
            100% 73%,
            83% 58%,
            67% 43%,
            52% 30%
        ) !important;
}

.urec-modern-top-shape::after {
    content: "" !important;

    position: absolute !important;
    top: 0 !important;
    right: 0 !important;

    width: 76% !important;
    height: 145px !important;

    background:
        linear-gradient(
            135deg,
            #d9ab3d 0%,
            #f0cf76 48%,
            #b88720 100%
        ) !important;

    clip-path:
        polygon(
            25% 0,
            100% 0,
            100% 32%,
            78% 45%,
            52% 29%
        ) !important;

    opacity: .96 !important;
}

.urec-modern-bottom-shape {
    bottom: 0 !important;
    height: 180px !important;

    background:
        linear-gradient(
            145deg,
            #004f24 0%,
            #007a33 65%,
            #173f27 100%
        ) !important;

    clip-path:
        polygon(
            0 58%,
            18% 71%,
            38% 86%,
            58% 100%,
            0 100%
        ) !important;
}

.urec-modern-bottom-shape::after {
    content: "" !important;

    position: absolute !important;
    left: 0 !important;
    bottom: 0 !important;

    width: 73% !important;
    height: 135px !important;

    background:
        linear-gradient(
            135deg,
            #d2a032 0%,
            #f0cc70 50%,
            #b98720 100%
        ) !important;

    clip-path:
        polygon(
            0 38%,
            24% 52%,
            50% 76%,
            73% 100%,
            0 100%
        ) !important;

    opacity: .96 !important;
}


/* =========================================================
   INNER BORDER
========================================================= */

.urec-modern-inner {
    position: relative !important;
    z-index: 2 !important;

    width: 100% !important;
    height: 100% !important;
    min-height: 1020px !important;

    padding: 62px 56px 54px !important;

    display: flex !important;
    flex-direction: column !important;

    border: 1.5px solid #213027 !important;
    border-radius: 4px !important;

    background:
        rgba(255, 255, 255, .92) !important;
}

.urec-modern-inner::before {
    content: "" !important;

    position: absolute !important;
    inset: 10px !important;

    border: 1px solid rgba(201, 157, 47, .55) !important;

    pointer-events: none !important;
}


/* =========================================================
   HEADER
========================================================= */

.urec-modern-header {
    position: relative !important;
    z-index: 2 !important;

    margin-bottom: 54px !important;

    text-align: center !important;
}

.urec-modern-republic {
    margin: 0 0 6px !important;

    color: #6f7972 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 700 !important;
    letter-spacing: .12em !important;
    text-transform: uppercase !important;
}

.urec-modern-header h2 {
    margin: 0 !important;

    color: #173020 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 24px !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    letter-spacing: -.02em !important;
}

.urec-modern-location {
    margin: 4px 0 0 !important;

    color: #4f5b53 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9px !important;
    font-weight: 700 !important;
    letter-spacing: .05em !important;
}

.urec-modern-office {
    margin: 13px 0 0 !important;

    color: #667169 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9px !important;
    font-weight: 600 !important;
}


/* =========================================================
   CERTIFICATE TITLE
========================================================= */

.urec-modern-title {
    position: relative !important;
    z-index: 2 !important;

    margin: 0 0 34px !important;

    text-align: center !important;
}

.urec-modern-title h3 {
    margin: 0 !important;

    color: #202820 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 35px !important;
    font-weight: 400 !important;
    line-height: 1.15 !important;
    letter-spacing: .13em !important;
    text-transform: uppercase !important;
}

.urec-modern-title span {
    display: block !important;

    margin-top: 7px !important;

    color: #007a33 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    letter-spacing: .12em !important;
    text-transform: uppercase !important;
}


/* =========================================================
   CERTIFICATE BODY
========================================================= */

.urec-modern-body {
    position: relative !important;
    z-index: 2 !important;

    max-width: 600px !important;
    margin: 0 auto !important;

    color: #414c44 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    font-weight: 400 !important;
    line-height: 1.8 !important;

    text-align: center !important;
}

.urec-modern-intro {
    margin: 0 0 14px !important;

    color: #59645d !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    line-height: 1.6 !important;
}

.urec-modern-research-title {
    max-width: 600px !important;

    margin: 8px auto 22px !important;
    padding: 0 8px 12px !important;

    border-bottom: 1px solid #b8a36b !important;

    color: #153320 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 17px !important;
    font-weight: 700 !important;
    line-height: 1.55 !important;
}

.urec-modern-body p {
    margin: 0 0 17px !important;
}

.urec-modern-body strong {
    color: #19221c !important;
    font-weight: 700 !important;
}


/* =========================================================
   CLASSIFICATION
========================================================= */

.urec-modern-classification-label {
    margin-top: 8px !important;

    color: #707a73 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 700 !important;
    letter-spacing: .07em !important;
    text-transform: uppercase !important;
}

.urec-modern-classification {
    display: inline-block !important;

    margin: 8px auto 8px !important;
    padding: 8px 24px !important;

    border-top: 1px solid #c9a84f !important;
    border-bottom: 1px solid #c9a84f !important;

    color: #006b2e !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 21px !important;
    font-weight: 800 !important;
    line-height: 1.3 !important;
}

.urec-modern-description {
    max-width: 560px !important;

    margin: 0 auto 20px !important;

    color: #69746c !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.2px !important;
    line-height: 1.65 !important;
}


/* =========================================================
   DETAILS
========================================================= */

.urec-modern-details {
    position: relative !important;
    z-index: 2 !important;

    max-width: 610px !important;

    margin: 30px auto 0 !important;
    padding-top: 18px !important;

    border-top: 1px solid #d9ded9 !important;
}

.urec-modern-detail {
    display: grid !important;
    grid-template-columns: 132px minmax(0, 1fr) !important;
    gap: 16px !important;

    padding: 7px 0 !important;
}

.urec-modern-detail span {
    color: #758078 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 7.8px !important;
    font-weight: 700 !important;
    letter-spacing: .06em !important;
    text-transform: uppercase !important;
}

.urec-modern-detail strong {
    color: #2b352e !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.3px !important;
    font-weight: 600 !important;
    line-height: 1.5 !important;
}


/* =========================================================
   DOCUMENT FOOTER
========================================================= */

.urec-modern-document-footer {
    position: relative !important;
    z-index: 2 !important;

    margin-top: auto !important;
    padding-top: 28px !important;

    text-align: center !important;
}

.urec-modern-system-note {
    max-width: 580px !important;

    margin: 0 auto !important;
    padding-top: 12px !important;

    border-top: 1px solid #d9ded9 !important;

    color: #78827b !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 7.8px !important;
    line-height: 1.6 !important;
}

.urec-modern-generated {
    margin-top: 10px !important;

    color: #69746c !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 7.6px !important;
    line-height: 1.55 !important;
}

.urec-modern-generated p {
    margin: 2px 0 !important;
}

.urec-modern-generated strong {
    color: #465049 !important;
    font-weight: 700 !important;
}


/* =========================================================
   SCREEN RESPONSIVE
========================================================= */

@media (max-width: 860px) {

    .urec-modern-page .generated-certificate-toolbar {
        align-items: flex-start !important;
        flex-direction: column !important;
    }

    .urec-modern-page .generated-certificate-actions {
        width: 100% !important;
    }

    .urec-modern-sheet {
        min-height: 0 !important;
        aspect-ratio: auto !important;

        padding: 16px !important;

        border-radius: 6px !important;
    }

    .urec-modern-inner {
        min-height: 900px !important;

        padding: 45px 28px 42px !important;
    }

    .urec-modern-header {
        margin-bottom: 42px !important;
    }

    .urec-modern-title h3 {
        font-size: 27px !important;
        letter-spacing: .09em !important;
    }

    .urec-modern-research-title {
        font-size: 14px !important;
    }

    .urec-modern-classification {
        font-size: 17px !important;
    }

    .urec-modern-detail {
        grid-template-columns: 1fr !important;
        gap: 2px !important;
    }
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    .urec-modern-page {
        max-width: none !important;
    }

    .urec-modern-page .generated-certificate-toolbar,
    .urec-modern-page .content-footer {
        display: none !important;
    }

    .urec-modern-sheet {
        width: 190mm !important;
        height: 267mm !important;
        min-height: 267mm !important;

        margin: 0 auto !important;
        padding: 7mm !important;

        border: 0 !important;
        border-radius: 0 !important;

        box-shadow: none !important;

        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .urec-modern-inner {
        min-height: 253mm !important;

        padding: 17mm 14mm 13mm !important;

        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .urec-modern-top-shape,
    .urec-modern-bottom-shape {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}



/* =========================================================
   STEP 2B — LARGER, BETTER-BALANCED PORTRAIT CERTIFICATE
   Uses more of the available portrait space.
   UI ONLY — no workflow/action changes.
========================================================= */

.urec-modern-sheet {
    width: min(100%, 820px) !important;
    min-height: 1120px !important;
    padding: 26px !important;
}

.urec-modern-inner {
    min-height: 1060px !important;
    padding: 58px 52px 48px !important;
}

/* Header */
.urec-modern-header {
    margin-bottom: 42px !important;
}

.urec-modern-republic {
    font-size: 10px !important;
    letter-spacing: .13em !important;
}

.urec-modern-header h2 {
    font-size: 30px !important;
}

.urec-modern-location {
    margin-top: 5px !important;
    font-size: 10.5px !important;
}

.urec-modern-office {
    margin-top: 12px !important;
    font-size: 11px !important;
}

/* Main certificate title */
.urec-modern-title {
    margin-bottom: 30px !important;
}

.urec-modern-title h3 {
    font-size: 44px !important;
    letter-spacing: .11em !important;
    line-height: 1.08 !important;
}

.urec-modern-title span {
    margin-top: 9px !important;
    font-size: 12px !important;
    letter-spacing: .11em !important;
}

/* Body */
.urec-modern-body {
    max-width: 660px !important;
    font-size: 13px !important;
    line-height: 1.85 !important;
}

.urec-modern-intro {
    margin-bottom: 13px !important;
    font-size: 11.5px !important;
}

.urec-modern-body p {
    margin-bottom: 16px !important;
}

/* Research title becomes a stronger focal point */
.urec-modern-research-title {
    max-width: 650px !important;
    margin: 8px auto 22px !important;
    padding: 0 12px 14px !important;

    font-size: 21px !important;
    line-height: 1.55 !important;
}

/* Classification */
.urec-modern-classification-label {
    margin-top: 5px !important;
    font-size: 9.5px !important;
}

.urec-modern-classification {
    margin: 8px auto 9px !important;
    padding: 9px 28px !important;

    font-size: 25px !important;
}

.urec-modern-description {
    max-width: 620px !important;
    margin-bottom: 18px !important;

    font-size: 10.8px !important;
    line-height: 1.7 !important;
}

/* Details use more width and larger type */
.urec-modern-details {
    max-width: 660px !important;
    margin-top: 24px !important;
    padding-top: 16px !important;
}

.urec-modern-detail {
    grid-template-columns: 145px minmax(0, 1fr) !important;
    gap: 18px !important;
    padding: 8px 0 !important;
}

.urec-modern-detail span {
    font-size: 8.8px !important;
}

.urec-modern-detail strong {
    font-size: 10.6px !important;
    line-height: 1.6 !important;
}

/* Footer moves up slightly and becomes easier to read */
.urec-modern-document-footer {
    padding-top: 20px !important;
}

.urec-modern-system-note {
    max-width: 630px !important;
    padding-top: 11px !important;

    font-size: 8.8px !important;
    line-height: 1.65 !important;
}

.urec-modern-generated {
    margin-top: 8px !important;
    font-size: 8.5px !important;
}

/* Let decorative areas use space more naturally */
.urec-modern-top-shape {
    height: 220px !important;
}

.urec-modern-bottom-shape {
    height: 195px !important;
}

/* Responsive tuning */
@media (max-width: 860px) {

    .urec-modern-sheet {
        width: 100% !important;
        min-height: 0 !important;
        padding: 14px !important;
    }

    .urec-modern-inner {
        min-height: 920px !important;
        padding: 42px 24px 38px !important;
    }

    .urec-modern-header h2 {
        font-size: 25px !important;
    }

    .urec-modern-title h3 {
        font-size: 34px !important;
    }

    .urec-modern-title span {
        font-size: 10px !important;
    }

    .urec-modern-body {
        font-size: 11.5px !important;
    }

    .urec-modern-research-title {
        font-size: 17px !important;
    }

    .urec-modern-classification {
        font-size: 20px !important;
        padding: 8px 18px !important;
    }

    .urec-modern-detail {
        grid-template-columns: 1fr !important;
        gap: 2px !important;
    }

    .urec-modern-detail strong {
        font-size: 10px !important;
    }
}

/* Print stays portrait but uses more of the physical page */
@media print {

    .urec-modern-sheet {
        width: 194mm !important;
        height: 274mm !important;
        min-height: 274mm !important;
        padding: 6mm !important;
    }

    .urec-modern-inner {
        min-height: 260mm !important;
        padding: 14mm 13mm 11mm !important;
    }

    .urec-modern-header h2 {
        font-size: 26px !important;
    }

    .urec-modern-title h3 {
        font-size: 37px !important;
    }

    .urec-modern-body {
        font-size: 11px !important;
    }

    .urec-modern-research-title {
        font-size: 17px !important;
    }

    .urec-modern-classification {
        font-size: 21px !important;
    }

    .urec-modern-detail strong {
        font-size: 9.6px !important;
    }
}



/* =========================================================
   STEP 2C — CERTIFICATE-ONLY TYPOGRAPHY UPGRADE
   Elegant serif accents are used ONLY inside the certificate.
   The rest of REMS remains Poppins + Century Gothic.
========================================================= */

/*
 * Formal university heading
 */
.urec-modern-header h2 {
    font-family:
        "Palatino Linotype",
        "Book Antiqua",
        Palatino,
        Georgia,
        serif !important;

    font-size: 31px !important;
    font-weight: 700 !important;
    letter-spacing: .01em !important;
}

/*
 * Main CERTIFICATE heading
 * More ceremonial and less like a normal web heading.
 */
.urec-modern-title h3 {
    font-family:
        Georgia,
        "Times New Roman",
        serif !important;

    font-size: 48px !important;
    font-weight: 500 !important;
    letter-spacing: .10em !important;
    line-height: 1.05 !important;
}

/*
 * Certificate subtitle stays structured and modern.
 */
.urec-modern-title span {
    font-family:
        "Century Gothic",
        Arial,
        sans-serif !important;

    font-size: 11.5px !important;
    font-weight: 700 !important;
    letter-spacing: .14em !important;
}

/*
 * Research title is the focal academic content.
 */
.urec-modern-research-title {
    font-family:
        "Palatino Linotype",
        "Book Antiqua",
        Palatino,
        Georgia,
        serif !important;

    font-size: 23px !important;
    font-weight: 600 !important;
    font-style: italic !important;
    line-height: 1.55 !important;

    letter-spacing: .005em !important;
}

/*
 * Classification should remain very readable.
 */
.urec-modern-classification {
    font-family:
        "Century Gothic",
        Arial,
        sans-serif !important;

    font-size: 25px !important;
    font-weight: 800 !important;
    letter-spacing: .015em !important;
}

/*
 * Intro / supporting text stays clean and readable.
 */
.urec-modern-body {
    font-family:
        "Century Gothic",
        Arial,
        sans-serif !important;
}

.urec-modern-intro,
.urec-modern-classification-label,
.urec-modern-detail span,
.urec-modern-system-note,
.urec-modern-generated {
    font-family:
        "Century Gothic",
        Arial,
        sans-serif !important;
}

/*
 * Slightly more formal detail values.
 */
.urec-modern-detail strong {
    font-family:
        "Century Gothic",
        Arial,
        sans-serif !important;

    font-weight: 600 !important;
}

/*
 * Add a very subtle visual divider under the title,
 * purely for certificate styling.
 */
.urec-modern-title::after {
    content: "" !important;

    display: block !important;

    width: 86px !important;
    height: 1px !important;

    margin: 15px auto 0 !important;

    background:
        linear-gradient(
            90deg,
            transparent,
            #c7a34a,
            transparent
        ) !important;
}

/*
 * Print keeps the same formal hierarchy.
 */
@media print {

    .urec-modern-header h2 {
        font-size: 27px !important;
    }

    .urec-modern-title h3 {
        font-size: 40px !important;
    }

    .urec-modern-research-title {
        font-size: 18px !important;
    }

    .urec-modern-classification {
        font-size: 21px !important;
    }
}



/* =========================================================
   STEP 3 — MODERN PORTRAIT RESEARCH PERMITS
   Same visual family as the UREC certificate:
   portrait, ASU green + gold, clean white center,
   elegant certificate-only typography.
========================================================= */

.rems-permit-page {
    width: 100% !important;
    max-width: 960px !important;
    margin: 0 auto !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
}


/* ---------------------------------------------------------
   TOOLBAR
--------------------------------------------------------- */

.rems-permit-page .generated-certificate-toolbar {
    margin: 0 0 18px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 16px !important;
}

.rems-permit-page .generated-certificate-toolbar h1 {
    margin: 0 0 4px !important;

    color: #18221b !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 22px !important;
    font-weight: 800 !important;
    line-height: 1.25 !important;
    letter-spacing: -.02em !important;
}

.rems-permit-page .generated-certificate-toolbar p {
    margin: 0 !important;

    color: #748078 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    line-height: 1.55 !important;
}

.rems-permit-page .generated-certificate-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
}

.rems-permit-page .generated-certificate-actions .btn {
    width: auto !important;
    min-height: 40px !important;

    padding: 0 13px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;

    border-radius: 10px !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
}


/* ---------------------------------------------------------
   PORTRAIT PERMIT SHEET
--------------------------------------------------------- */

.rems-permit-sheet {
    position: relative !important;

    width: min(100%, 820px) !important;
    aspect-ratio: 210 / 297 !important;
    min-height: 1120px !important;

    margin: 0 auto !important;
    padding: 26px !important;

    overflow: hidden !important;

    border: 1px solid #d8ddd9 !important;
    border-radius: 8px !important;

    background:
        radial-gradient(
            circle at 18% 25%,
            rgba(0,122,51,.018) 0,
            rgba(0,122,51,.018) 1px,
            transparent 1px
        ),
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fffefa 100%
        ) !important;

    background-size:
        13px 13px,
        auto !important;

    box-shadow:
        0 18px 46px rgba(20, 37, 25, .10) !important;
}


/* ---------------------------------------------------------
   GREEN + GOLD CORNER CURVES
--------------------------------------------------------- */

.rems-permit-top-shape,
.rems-permit-bottom-shape {
    position: absolute !important;

    left: 0 !important;
    right: 0 !important;

    z-index: 0 !important;

    pointer-events: none !important;
}

.rems-permit-top-shape {
    top: 0 !important;
    height: 220px !important;

    background:
        linear-gradient(
            132deg,
            #004f24 0%,
            #007a33 58%,
            #1c8e3f 100%
        ) !important;

    clip-path:
        polygon(
            38% 0,
            100% 0,
            100% 73%,
            83% 58%,
            67% 43%,
            52% 30%
        ) !important;
}

.rems-permit-top-shape::after {
    content: "" !important;

    position: absolute !important;

    top: 0 !important;
    right: 0 !important;

    width: 76% !important;
    height: 150px !important;

    background:
        linear-gradient(
            135deg,
            #d9ab3d 0%,
            #f0cf76 48%,
            #b88720 100%
        ) !important;

    clip-path:
        polygon(
            25% 0,
            100% 0,
            100% 32%,
            78% 45%,
            52% 29%
        ) !important;
}

.rems-permit-bottom-shape {
    bottom: 0 !important;
    height: 195px !important;

    background:
        linear-gradient(
            145deg,
            #004f24 0%,
            #007a33 65%,
            #173f27 100%
        ) !important;

    clip-path:
        polygon(
            0 58%,
            18% 71%,
            38% 86%,
            58% 100%,
            0 100%
        ) !important;
}

.rems-permit-bottom-shape::after {
    content: "" !important;

    position: absolute !important;

    left: 0 !important;
    bottom: 0 !important;

    width: 73% !important;
    height: 140px !important;

    background:
        linear-gradient(
            135deg,
            #d2a032 0%,
            #f0cc70 50%,
            #b98720 100%
        ) !important;

    clip-path:
        polygon(
            0 38%,
            24% 52%,
            50% 76%,
            73% 100%,
            0 100%
        ) !important;
}


/* ---------------------------------------------------------
   INNER FRAME
--------------------------------------------------------- */

.rems-permit-inner {
    position: relative !important;
    z-index: 2 !important;

    width: 100% !important;
    height: 100% !important;
    min-height: 1060px !important;

    padding: 58px 52px 48px !important;

    display: flex !important;
    flex-direction: column !important;

    border: 1.5px solid #213027 !important;
    border-radius: 4px !important;

    background:
        rgba(255,255,255,.92) !important;
}

.rems-permit-inner::before {
    content: "" !important;

    position: absolute !important;

    inset: 10px !important;

    border: 1px solid rgba(201,157,47,.55) !important;

    pointer-events: none !important;
}


/* ---------------------------------------------------------
   INSTITUTION HEADER
--------------------------------------------------------- */

.rems-permit-header {
    position: relative !important;
    z-index: 2 !important;

    margin-bottom: 40px !important;

    text-align: center !important;
}

.rems-permit-republic {
    margin: 0 0 6px !important;

    color: #6f7972 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    letter-spacing: .13em !important;
    text-transform: uppercase !important;
}

.rems-permit-header h2 {
    margin: 0 !important;

    color: #173020 !important;

    font-family:
        "Palatino Linotype",
        "Book Antiqua",
        Palatino,
        Georgia,
        serif !important;

    font-size: 31px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    letter-spacing: .01em !important;
}

.rems-permit-location {
    margin: 5px 0 0 !important;

    color: #4f5b53 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    letter-spacing: .05em !important;
}

.rems-permit-office {
    margin: 12px 0 0 !important;

    color: #667169 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
}


/* ---------------------------------------------------------
   PERMIT TITLE
--------------------------------------------------------- */

.rems-permit-title {
    position: relative !important;
    z-index: 2 !important;

    margin: 0 0 28px !important;

    text-align: center !important;
}

.rems-permit-title h3 {
    margin: 0 !important;

    color: #202820 !important;

    font-family:
        Georgia,
        "Times New Roman",
        serif !important;

    font-size: 43px !important;
    font-weight: 500 !important;
    line-height: 1.08 !important;
    letter-spacing: .09em !important;
    text-transform: uppercase !important;
}

.rems-permit-title span {
    display: block !important;

    margin-top: 8px !important;

    color: #007a33 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    letter-spacing: .13em !important;
    text-transform: uppercase !important;
}

.rems-permit-title::after {
    content: "" !important;

    display: block !important;

    width: 86px !important;
    height: 1px !important;

    margin: 14px auto 0 !important;

    background:
        linear-gradient(
            90deg,
            transparent,
            #c7a34a,
            transparent
        ) !important;
}


/* ---------------------------------------------------------
   BODY
--------------------------------------------------------- */

.rems-permit-body {
    position: relative !important;
    z-index: 2 !important;

    max-width: 660px !important;
    margin: 0 auto !important;

    color: #414c44 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 12.5px !important;
    font-weight: 400 !important;
    line-height: 1.85 !important;

    text-align: center !important;
}

.rems-permit-body p {
    margin: 0 0 16px !important;
}

.rems-permit-body strong {
    color: #19221c !important;
    font-weight: 700 !important;
}

.rems-permit-intro {
    color: #59645d !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    line-height: 1.6 !important;
}

.rems-permit-research-title {
    max-width: 650px !important;

    margin: 8px auto 22px !important;
    padding: 0 12px 14px !important;

    border-bottom: 1px solid #b8a36b !important;

    color: #153320 !important;

    font-family:
        "Palatino Linotype",
        "Book Antiqua",
        Palatino,
        Georgia,
        serif !important;

    font-size: 22px !important;
    font-weight: 600 !important;
    font-style: italic !important;
    line-height: 1.55 !important;
}


/* ---------------------------------------------------------
   PERMIT TYPE / CLEARANCE
--------------------------------------------------------- */

.rems-permit-clearance-label {
    margin-top: 4px !important;

    color: #707a73 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.8px !important;
    font-weight: 700 !important;
    letter-spacing: .07em !important;
    text-transform: uppercase !important;
}

.rems-permit-clearance {
    display: inline-block !important;

    margin: 8px auto 10px !important;
    padding: 9px 28px !important;

    border-top: 1px solid #c9a84f !important;
    border-bottom: 1px solid #c9a84f !important;

    color: #006b2e !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 22px !important;
    font-weight: 800 !important;
    line-height: 1.35 !important;
}

.rems-permit-description {
    max-width: 610px !important;

    margin: 0 auto 18px !important;

    color: #69746c !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    line-height: 1.7 !important;
}


/* ---------------------------------------------------------
   DETAILS
--------------------------------------------------------- */

.rems-permit-details {
    position: relative !important;
    z-index: 2 !important;

    max-width: 660px !important;

    margin: 24px auto 0 !important;
    padding-top: 16px !important;

    border-top: 1px solid #d9ded9 !important;
}

.rems-permit-detail {
    display: grid !important;
    grid-template-columns: 145px minmax(0,1fr) !important;
    gap: 18px !important;

    padding: 8px 0 !important;
}

.rems-permit-detail span {
    color: #758078 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.8px !important;
    font-weight: 700 !important;
    letter-spacing: .06em !important;
    text-transform: uppercase !important;
}

.rems-permit-detail strong {
    color: #2b352e !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.6px !important;
    font-weight: 600 !important;
    line-height: 1.6 !important;
}


/* ---------------------------------------------------------
   OPTIONAL RPS REMARKS
--------------------------------------------------------- */

.rems-permit-remarks {
    max-width: 660px !important;

    margin: 18px auto 0 !important;
    padding: 13px 15px !important;

    border: 1px solid #e2e7e3 !important;
    border-radius: 10px !important;

    background: #fbfcfb !important;

    color: #505b53 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    line-height: 1.65 !important;

    text-align: left !important;
}

.rems-permit-remarks strong {
    color: #28332b !important;
}


/* ---------------------------------------------------------
   DOCUMENT FOOTER
--------------------------------------------------------- */

.rems-permit-document-footer {
    position: relative !important;
    z-index: 2 !important;

    margin-top: auto !important;
    padding-top: 20px !important;

    text-align: center !important;
}

.rems-permit-system-note {
    max-width: 630px !important;

    margin: 0 auto !important;
    padding-top: 11px !important;

    border-top: 1px solid #d9ded9 !important;

    color: #78827b !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.8px !important;
    line-height: 1.65 !important;
}

.rems-permit-generated {
    margin-top: 8px !important;

    color: #69746c !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    line-height: 1.55 !important;
}

.rems-permit-generated p {
    margin: 2px 0 !important;
}

.rems-permit-generated strong {
    color: #465049 !important;
    font-weight: 700 !important;
}


/* ---------------------------------------------------------
   RESPONSIVE
--------------------------------------------------------- */

@media (max-width: 860px) {

    .rems-permit-page .generated-certificate-toolbar {
        align-items: flex-start !important;
        flex-direction: column !important;
    }

    .rems-permit-page .generated-certificate-actions {
        width: 100% !important;
    }

    .rems-permit-sheet {
        width: 100% !important;
        min-height: 0 !important;
        aspect-ratio: auto !important;

        padding: 14px !important;
    }

    .rems-permit-inner {
        min-height: 920px !important;

        padding: 42px 24px 38px !important;
    }

    .rems-permit-header h2 {
        font-size: 25px !important;
    }

    .rems-permit-title h3 {
        font-size: 33px !important;
    }

    .rems-permit-body {
        font-size: 11.5px !important;
    }

    .rems-permit-research-title {
        font-size: 17px !important;
    }

    .rems-permit-clearance {
        font-size: 18px !important;
        padding: 8px 18px !important;
    }

    .rems-permit-detail {
        grid-template-columns: 1fr !important;
        gap: 2px !important;
    }
}


/* ---------------------------------------------------------
   PRINT
--------------------------------------------------------- */

@media print {

    .rems-permit-page {
        max-width: none !important;
    }

    .rems-permit-page .generated-certificate-toolbar,
    .rems-permit-page .content-footer {
        display: none !important;
    }

    .rems-permit-sheet {
        width: 194mm !important;
        height: 274mm !important;
        min-height: 274mm !important;

        margin: 0 auto !important;
        padding: 6mm !important;

        border: 0 !important;
        border-radius: 0 !important;

        box-shadow: none !important;

        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .rems-permit-inner {
        min-height: 260mm !important;

        padding: 14mm 13mm 11mm !important;

        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .rems-permit-top-shape,
    .rems-permit-bottom-shape {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .rems-permit-header h2 {
        font-size: 27px !important;
    }

    .rems-permit-title h3 {
        font-size: 36px !important;
    }

    .rems-permit-body {
        font-size: 11px !important;
    }

    .rems-permit-research-title {
        font-size: 18px !important;
    }

    .rems-permit-clearance {
        font-size: 19px !important;
    }
}



/* =========================================================
   STEP 3B — NOTICE TO PROCEED
   Same portrait family as the research permits.
========================================================= */

.rems-ntp-page .rems-permit-title h3 {
    letter-spacing: .08em !important;
}

.rems-ntp-page .rems-permit-clearance {
    min-width: 260px !important;
}

.rems-ntp-system-note {
    margin-top: 8px !important;
    border-top: 0 !important;
    padding-top: 4px !important;
}





/* =========================================================
   CERTIFICATE LAYOUT UNIFICATION
   Apply the modern UREC/IACUC certificate language to all
   generated certificates and permits, and use Open Sans.
========================================================= */

.generated-certificate-page,
.generated-certificate-page .generated-certificate-toolbar,
.generated-certificate-page .generated-certificate-toolbar h1,
.generated-certificate-page .generated-certificate-toolbar p,
.generated-certificate-page .generated-certificate-sheet,
.generated-certificate-page .generated-certificate-sheet * {
    font-family: "Open Sans", Arial, sans-serif !important;
}

.urec-modern-header h2,
.urec-modern-title h3,
.urec-modern-research-title,
.urec-modern-classification,
.urec-modern-detail strong {
    font-family: "Open Sans", Arial, sans-serif !important;
}

.urec-modern-title h3,
.urec-modern-header h2,
.urec-modern-research-title,
.urec-modern-classification {
    letter-spacing: 0 !important;
}

.urec-modern-title h3 {
    font-weight: 800 !important;
}


/* =========================================================
   STEP 5 — FINAL REMS UI CONSISTENCY PASS
   UI ONLY.
   No workflow, PHP actions, SQL, statuses, redirects,
   permissions, uploads, or button behavior are changed.
========================================================= */

:root {
    --rems-final-green: #007a33;
    --rems-final-green-dark: #005f28;
    --rems-final-gold: #c9a34a;
    --rems-final-text: #1b241e;
    --rems-final-muted: #6d786f;
    --rems-final-border: #dfe7e1;
    --rems-final-soft: #f7faf8;
    --rems-final-white: #ffffff;
    --rems-final-shadow: 0 12px 30px rgba(19, 55, 31, .055);
}


/* =========================================================
   TYPOGRAPHY
   Main REMS UI stays Poppins + Century Gothic.
   The approved serif fonts inside generated documents
   are intentionally NOT overridden.
========================================================= */

.research-view,
.research-view button,
.research-view input,
.research-view select,
.research-view textarea {
    font-family: "Century Gothic", Arial, sans-serif;
}

.research-view-heading h1,
.research-panel-title,
.workflow-text h4,
.card-title {
    letter-spacing: -.015em;
}

.research-view-heading p,
.workflow-text p,
.info-item span,
.form-text,
.text-muted {
    line-height: 1.6;
}

.research-view label,
.research-view .form-label,
.research-view .info-item label,
.research-view .small-label {
    font-family: "Century Gothic", Arial, sans-serif;
}


/* =========================================================
   MAIN PAGE SPACING
========================================================= */

.page-content {
    padding-top: 24px !important;
    padding-bottom: 34px !important;
}

.research-view {
    width: 100% !important;
}

.research-view-heading {
    margin-bottom: 18px !important;
}

.research-view-heading h1 {
    margin-bottom: 4px !important;
}

.research-view-heading p {
    margin-bottom: 0 !important;
}


/* More even spacing between major content sections */
.research-view > section + section,
.research-view > .research-panel + .research-panel,
.research-view > .workflow-card + .workflow-card {
    margin-top: 18px !important;
}


/* =========================================================
   CARDS / PANELS
========================================================= */

.research-panel,
.workflow-card,
.research-document-viewer,
.card,
.asu-card,
.processing-card,
.project-info-card,
.progress-form-card,
.progress-history-card,
.progress-update-card {
    border-radius: 18px !important;
    border-color: rgba(0, 107, 46, .13) !important;
    box-shadow: var(--rems-final-shadow) !important;
}

.research-panel,
.workflow-card {
    padding: 20px !important;
}

.research-panel-header,
.workflow-content {
    gap: 14px !important;
}

.research-panel-title,
.workflow-text h4 {
    margin-bottom: 4px !important;
}


/* Keep the left workflow accent restrained and consistent */
.workflow-card {
    border-left-width: 3px !important;
}


/* =========================================================
   BUTTON CONSISTENCY
========================================================= */

.research-view .btn:not(.btn-sm),
.generated-certificate-toolbar .btn {
    min-height: 40px;
    padding: 0 14px;
    border-radius: 10px !important;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;

    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;

    box-shadow: none;
}

.research-view .btn-asu,
.research-view .btn.btn-asu,
.generated-certificate-toolbar .btn-asu {
    box-shadow: 0 6px 14px rgba(0, 107, 46, .12) !important;
}

.research-view .btn-outline-secondary {
    border-color: #d7dfd9 !important;
    background: #ffffff !important;
    color: #505b53 !important;
}

.research-view .btn-outline-secondary:hover {
    border-color: #c2cdc5 !important;
    background: #f8faf8 !important;
    color: #273229 !important;
}

.research-view .btn:focus-visible,
.generated-certificate-toolbar .btn:focus-visible {
    outline: 3px solid rgba(0, 122, 51, .15) !important;
    outline-offset: 2px !important;
}


/* =========================================================
   FORM CONTROLS
========================================================= */

.research-view .form-control,
.research-view .form-select {
    min-height: 43px;

    border: 1px solid #d8e1da;
    border-radius: 10px;

    background-color: #ffffff;
    color: var(--rems-final-text);

    font-size: 11.5px;
}

.research-view textarea.form-control {
    min-height: 105px;
    padding-top: 11px;
    padding-bottom: 11px;
    resize: vertical;
}

.research-view .form-control::placeholder {
    color: #9aa39c;
}

.research-view .form-control:focus,
.research-view .form-select:focus {
    border-color: var(--rems-final-green) !important;
    box-shadow: 0 0 0 .18rem rgba(0, 122, 51, .10) !important;
}

.research-view .form-label {
    margin-bottom: 6px;

    color: #465149;

    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: .025em;
}

.research-view .form-text {
    margin-top: 5px;

    color: #7b857e;

    font-size: 9px;
}


/* =========================================================
   ALERTS / FEEDBACK MESSAGES
   Plain, restrained, and consistent with the approved popup.
========================================================= */

.research-view .alert {
    margin-bottom: 16px;
    padding: 12px 14px;

    border-width: 1px !important;
    border-style: solid !important;
    border-radius: 11px !important;

    background-image: none !important;

    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 10.5px;
    line-height: 1.6;

    box-shadow: none !important;
}

.research-view .alert-success {
    border-color: #cce3d3 !important;
    background: #f4faf6 !important;
    color: #275b37 !important;
}

.research-view .alert-warning {
    border-color: #e7ddbd !important;
    background: #fcfaf4 !important;
    color: #66562d !important;
}

.research-view .alert-danger {
    border-color: #ebd3d3 !important;
    background: #fdf7f7 !important;
    color: #7a3f3f !important;
}

.research-view .alert-info {
    border-color: #d7e4dc !important;
    background: #f6f9f7 !important;
    color: #42584a !important;
}

.research-view .alert:last-child {
    margin-bottom: 0;
}


/* =========================================================
   STATUS / SMALL META ELEMENTS
========================================================= */

.research-status,
.project-status,
.monitoring-status,
.rps-workflow-badge,
.processing-count,
.monitoring-count {
    border-radius: 999px !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 700 !important;
    letter-spacing: .02em !important;
}

.info-item label {
    margin-bottom: 4px !important;

    font-size: 8.5px !important;
    font-weight: 700 !important;
    letter-spacing: .035em !important;
    text-transform: uppercase;
}

.info-item span,
.info-item strong {
    font-size: 11px;
    line-height: 1.55;
}


/* =========================================================
   TABLE / FILE / DOCUMENT VIEWER POLISH
========================================================= */

.research-view table {
    font-size: 10.5px;
}

.research-view table th {
    font-family: "Century Gothic", Arial, sans-serif;
    font-size: 8.5px;
    font-weight: 700;
    letter-spacing: .035em;
    text-transform: uppercase;
}

.research-view table td,
.research-view table th {
    vertical-align: middle;
}

.research-document-viewer {
    overflow: hidden;
}

.document-icon,
.document-open-icon {
    border-radius: 10px !important;
}


/* =========================================================
   GENERATED DOCUMENT TOOLBARS
   Certificate / NTP / permits now feel like one document set.
========================================================= */

.urec-modern-page .generated-certificate-toolbar,
.rems-permit-page .generated-certificate-toolbar {
    padding: 0 2px !important;
    margin-bottom: 16px !important;
}

.urec-modern-page .generated-certificate-toolbar h1,
.rems-permit-page .generated-certificate-toolbar h1 {
    font-size: 21px !important;
}

.urec-modern-page .generated-certificate-toolbar p,
.rems-permit-page .generated-certificate-toolbar p {
    max-width: 560px;
    font-size: 9.5px !important;
}

.urec-modern-sheet,
.rems-permit-sheet {
    box-shadow: 0 16px 40px rgba(20, 37, 25, .09) !important;
}


/* =========================================================
   CONTENT FOOTER
========================================================= */

.content-footer {
    padding-top: 18px !important;
    padding-bottom: 8px !important;

    color: #778179 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
}

.content-footer .footer-divider {
    opacity: .45;
}


/* =========================================================
   MODALS — FINAL CONSISTENCY
   Preserve Step 1's plain left-accent design.
========================================================= */

#submitModal .modal-title,
#resubmitModal .modal-title,
#rpsSectionCommentModal .modal-title,
#sectionRemarkViewModal .modal-title {
    letter-spacing: -.01em !important;
}

#submitModal .modal-footer,
#resubmitModal .modal-footer,
#rpsSectionCommentModal .modal-footer,
#sectionRemarkViewModal .modal-footer {
    gap: 8px !important;
}

#submitModal .modal-footer .btn,
#resubmitModal .modal-footer .btn,
#rpsSectionCommentModal .modal-footer .btn,
#sectionRemarkViewModal .modal-footer .btn {
    min-height: 40px !important;
    border-radius: 10px !important;
}


/* =========================================================
   RESPONSIVE CLEANUP
========================================================= */

@media (max-width: 900px) {

    .page-content {
        padding-top: 18px !important;
        padding-bottom: 26px !important;
    }

    .research-panel,
    .workflow-card {
        padding: 17px !important;
    }

    .generated-certificate-actions {
        width: 100%;
    }
}

@media (max-width: 640px) {

    .page-content {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    .research-view-heading {
        margin-bottom: 14px !important;
    }

    .research-panel,
    .workflow-card,
    .card,
    .asu-card {
        border-radius: 14px !important;
    }

    .research-panel,
    .workflow-card {
        padding: 15px !important;
    }

    .research-view .btn:not(.btn-sm) {
        min-height: 41px;
    }

    .generated-certificate-toolbar {
        align-items: stretch !important;
    }

    .generated-certificate-actions {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 8px !important;
    }

    .generated-certificate-actions .btn {
        width: 100% !important;
    }

    .content-footer {
        flex-wrap: wrap !important;
        justify-content: center !important;
        text-align: center !important;
        row-gap: 4px !important;
    }
}


/* =========================================================
   PRINT CLEANUP
   Only visual cleanup; generated document content stays intact.
========================================================= */

@media print {

    html,
    body {
        background: #ffffff !important;
    }

    .urec-modern-page,
    .rems-permit-page {
        background: #ffffff !important;
    }

    .urec-modern-sheet,
    .rems-permit-sheet {
        box-shadow: none !important;
    }

    .urec-modern-page .generated-certificate-toolbar,
    .rems-permit-page .generated-certificate-toolbar,
    .urec-modern-page .content-footer,
    .rems-permit-page .content-footer {
        display: none !important;
    }
}



/* =========================================================
   SELF-INITIATED AJAX UPLOAD UX
   No page refresh / no visible auto-scroll.
========================================================= */

section.workflow-card.upe-step-card {
    overflow-anchor: none;
}

.upe-upload-form button[aria-busy="true"] {
    cursor: wait !important;
    opacity: .88 !important;
}

.upe-upload-form button[aria-busy="true"] .spinner-border {
    width: .85rem !important;
    height: .85rem !important;
    border-width: .12em !important;
}

.rems-inline-upload-error {
    margin: 12px 0 0 !important;
}



/* =========================================================
   UPLOAD VIEWPORT LOCK
   Keep the user's exact visible area during AJAX upload.
========================================================= */

html,
body {
    overflow-anchor: auto;
}

section.workflow-card.upe-step-card,
section.workflow-card.upe-step-card * {
    scroll-margin-top: 0 !important;
}

section.workflow-card.upe-step-card {
    overflow-anchor: none !important;
}



/* =========================================================
   VIEW.PHP — MATCH PROJECT MONITORING DESIGN
   UI ONLY — no workflow/database/action logic changed.
========================================================= */

/* Same topbar family used by Project Monitoring */
.rems-uniform-topbar,
.asu-dashboard-topbar {
    /* Keep the Research Details header visible during page scrolling. */
    position: sticky !important;
    top: 0 !important;
    z-index: 1030 !important;
    min-height: 76px !important;
    height: 76px !important;
    padding: 0 30px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 20px !important;

    border-bottom: 1px solid rgba(0, 122, 51, .12) !important;
    background: rgba(255, 255, 255, .97) !important;
    box-shadow: 0 10px 26px rgba(16, 24, 16, .035) !important;
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
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 17px !important;
    font-weight: 800 !important;
    line-height: 1.15 !important;
    letter-spacing: -.015em !important;
}

.asu-dashboard-brandline span {
    display: block !important;
    margin-top: 4px !important;
    color: #657068 !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 13px !important;
    font-weight: 400 !important;
    line-height: 1.15 !important;
}

.asu-dashboard-actions,
.dashboard-user-mini {
    display: flex !important;
    align-items: center !important;
}

.asu-dashboard-actions {
    justify-content: flex-end !important;
    gap: 12px !important;
}

.dashboard-user-mini {
    gap: 12px !important;
}

.dashboard-user-copy {
    text-align: right !important;
}

.dashboard-user-copy strong {
    display: block !important;
    color: #101810 !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    line-height: 1.1 !important;
}

.dashboard-user-copy span {
    display: block !important;
    margin-top: 4px !important;
    color: #657068 !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
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

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 15px !important;
    font-weight: 900 !important;

    box-shadow: 0 8px 18px rgba(255, 213, 31, .25) !important;
}


/* Research Manuscript Details uses the Project Monitoring banner style */
.research-view-heading {
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

    box-shadow: 0 18px 42px rgba(0, 90, 40, .18) !important;
}

.research-view-heading::before {
    content: "" !important;
    position: absolute !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 29% !important;

    background:
        linear-gradient(
            135deg,
            rgba(255, 213, 31, .11),
            rgba(255, 255, 255, .025)
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

.research-view-heading > div,
.research-view-heading > a,
.research-view-heading .workflow-buttons {
    position: relative !important;
    z-index: 2 !important;
}

.research-view-heading h1 {
    margin: 0 0 5px !important;
    color: #ffffff !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 30px !important;
    font-weight: 900 !important;
    line-height: 1.15 !important;
    letter-spacing: -.025em !important;
}

.research-view-heading p {
    margin: 0 !important;
    max-width: 760px !important;
    color: rgba(255, 255, 255, .82) !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10.5px !important;
    line-height: 1.55 !important;
}

/* White action button like Project Monitoring */
.research-view-heading > .btn,
.research-view-heading > .btn-outline-asu,
.research-view-heading > .btn.btn-outline-asu,
.research-view-heading .workflow-buttons .btn,
.research-view-heading .workflow-buttons .btn-outline-asu {
    min-height: 40px !important;
    padding: 0 14px !important;

    border: 1px solid rgba(255, 255, 255, .95) !important;
    border-radius: 10px !important;

    background: rgba(255, 255, 255, .96) !important;
    color: #164b2a !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 800 !important;

    box-shadow: 0 8px 18px rgba(0, 65, 27, .10) !important;
}

.research-view-heading > .btn i,
.research-view-heading > .btn-outline-asu i,
.research-view-heading .workflow-buttons .btn i {
    color: #164b2a !important;
}

.research-view-heading > .btn:hover,
.research-view-heading > .btn-outline-asu:hover,
.research-view-heading .workflow-buttons .btn:hover {
    background: #ffffff !important;
    border-color: #ffffff !important;
    color: #007a33 !important;
}


/* Responsive behavior kept consistent with progress.php */
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

    .research-view-heading {
        min-height: 92px !important;
        align-items: flex-start !important;
        flex-direction: column !important;
        padding: 18px !important;
    }

    .research-view-heading::before {
        width: 38% !important;
        opacity: .78 !important;
    }

    .research-view-heading h1 {
        font-size: 25px !important;
    }

    .research-view-heading > .btn,
    .research-view-heading .workflow-buttons {
        width: 100% !important;
    }

    .research-view-heading .workflow-buttons {
        display: flex !important;
        flex-direction: column !important;
    }

    .research-view-heading > .btn,
    .research-view-heading .workflow-buttons .btn {
        width: 100% !important;
    }
}



/* =========================================================
   VIEW.PHP — MATCH THE LOWER SUMMARY BOX TO progress.php
   UI ONLY — no workflow/database/action logic changed.
========================================================= */

.research-summary {
    position: relative !important;
    overflow: hidden !important;

    margin-bottom: 18px !important;
    padding: 20px 22px !important;

    border: 1px solid rgba(0, 122, 51, .13) !important;
    border-left: 1px solid rgba(0, 122, 51, .13) !important;
    border-radius: 18px !important;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fbfdfb 100%
        ) !important;

    color: #1f2922 !important;

    box-shadow:
        0 14px 34px rgba(16, 24, 16, .055) !important;
}

/* Remove the decorative green overlay used by the old summary card */
.research-summary::before,
.research-summary::after {
    display: none !important;
    content: none !important;
}

.research-summary-top {
    position: relative !important;
    z-index: 1 !important;

    display: flex !important;
    align-items: flex-start !important;
    justify-content: space-between !important;

    gap: 18px !important;
}

.research-summary h2 {
    margin: 0 0 8px !important;
    max-width: 800px !important;

    color: #101810 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 20px !important;
    font-weight: 800 !important;
    line-height: 1.4 !important;
}

.research-summary-meta {
    display: flex !important;
    flex-wrap: wrap !important;

    gap: 7px 16px !important;

    color: #657068 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 9.5px !important;
    font-weight: 600 !important;
}

.research-summary-meta span {
    color: #657068 !important;
}

.research-summary-meta i {
    color: #007a33 !important;
}

/* Same yellow status pill used on progress.php */
.research-summary .research-status {
    position: relative !important;
    z-index: 1 !important;

    min-height: 30px !important;
    padding: 6px 11px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border: 1px solid #ffd51f !important;
    border-radius: 999px !important;

    background: #ffd51f !important;
    color: #003f1a !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 700 !important;

    box-shadow:
        0 8px 18px rgba(255, 213, 31, .18) !important;
}

/* Match progress.php's clean progress area */
.research-summary .research-progress,
.research-summary .workflow-status-strip {
    position: relative !important;
    z-index: 1 !important;

    margin-top: 18px !important;
    padding-top: 14px !important;

    border-top: 1px solid #edf1ee !important;

    background: transparent !important;
}

.research-summary .research-progress-head,
.research-summary .workflow-status-strip .research-progress-head {
    margin-bottom: 7px !important;

    color: #68736b !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 8.5px !important;
    font-weight: 700 !important;
}

.research-summary .research-progress-head span,
.research-summary .workflow-status-strip .research-progress-head span {
    color: #d29f00 !important;
    font-weight: 700 !important;
}

.research-summary .research-progress-head small,
.research-summary .workflow-status-strip .research-progress-head small {
    color: #88918b !important;
}

.research-summary .research-progress-head strong,
.research-summary .workflow-status-strip .research-progress-head strong {
    padding: 0 !important;

    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;

    color: #007a33 !important;

    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 12px !important;
    font-weight: 800 !important;
}

.research-summary .research-progress-bar {
    height: 8px !important;

    overflow: hidden !important;

    border-radius: 999px !important;

    background: #e6eee8 !important;
}

.research-summary .research-progress-value {
    height: 100% !important;

    border-radius: 999px !important;

    background:
        linear-gradient(
            90deg,
            #007a33 0%,
            #2c9a39 100%
        ) !important;
}

@media (max-width: 760px) {
    .research-summary-top {
        align-items: flex-start !important;
        flex-direction: column !important;
    }
}



/* =========================================================
   BACK TO RESEARCH ONLY
   Exact button style from Research Management > New Proposal
========================================================= */
.research-view-heading > .btn.btn-outline-asu.back-to-research-new-proposal-style {
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

.research-view-heading > .btn.btn-outline-asu.back-to-research-new-proposal-style i {
    color: #003f1a !important;
}

.research-view-heading > .btn.btn-outline-asu.back-to-research-new-proposal-style:hover {
    background: #f0bd00 !important;
    color: #003f1a !important;
}



/* =========================================================
   MANUSCRIPT SUMMARY — LONG FILENAME WRAP ONLY
   Prevents long manuscript filenames from overflowing.
========================================================= */

.phase-one-text {
    min-width: 0 !important;
    flex: 1 1 auto !important;
}

.phase-one-text strong {
    max-width: 100% !important;
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
}



/* Only Phase 1 document summary cards are clickable. No JavaScript needed. */
.phase-one-item.phase-one-clickable,
.phase-one-item.phase-one-clickable:visited {
    display: flex;
    text-decoration: none !important;
    color: #1c251f !important;
    cursor: pointer;
    transition: border-color .16s ease, background .16s ease, box-shadow .16s ease;
}
.phase-one-item.phase-one-clickable:hover,
.phase-one-item.phase-one-clickable:focus-visible {
    background: #eaf7ee !important;
    border-color: #64ad7e !important;
    box-shadow: 0 5px 14px rgba(0, 107, 46, .09);
}
.phase-one-item.phase-one-clickable:focus-visible {
    outline: 2px solid #087b37;
    outline-offset: 3px;
}
.phase-one-item.phase-one-clickable .phase-one-view-label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 3px;
    color: #087b37;
    font-size: 11px;
    font-weight: 800;
}
.phase-one-item.phase-one-clickable:hover .phase-one-view-label {
    text-decoration: underline;
}

/* =========================================================
   VIEW.PHP — STEADY SCROLL + COMPACT SPACING ONLY
   No workflow/database/status/upload logic changed.
========================================================= */

/* Same steady scrolling used on the RPS pages */
html,
body {
    overscroll-behavior-x: none !important;
    overscroll-behavior-y: none !important;
}

.page-content {
    overscroll-behavior-y: contain !important;
}


/* Reduce only the excessive vertical spacing on this page */
.page-content {
    padding-top: 18px !important;
    padding-bottom: 24px !important;
}

.research-view-heading {
    margin-bottom: 14px !important;
}

.research-summary {
    margin-bottom: 14px !important;
    padding: 16px 20px !important;
}

.research-summary .research-progress,
.research-summary .workflow-status-strip {
    margin-top: 14px !important;
    padding-top: 11px !important;
}


/* Manuscript Upload Summary had double padding:
   research-panel padding + its own header/body padding.
   Remove only that extra outer padding. */
.phase-one-summary-panel {
    padding: 0 !important;
    margin-top: 14px !important;
    margin-bottom: 14px !important;
}

.phase-one-summary-panel .research-panel-header {
    min-height: 56px !important;
    padding: 12px 18px !important;
}

.phase-one-summary-panel .research-panel-body {
    padding: 14px 18px 16px !important;
}

.phase-one-summary-panel .phase-one-grid {
    gap: 10px !important;
}

.phase-one-summary-panel .phase-one-item {
    min-height: 78px !important;
    padding: 11px !important;
}


/* Keep mobile spacing comfortable */
@media (max-width: 760px) {
    .page-content {
        padding-top: 14px !important;
        padding-bottom: 20px !important;
    }

    .research-view-heading,
    .research-summary,
    .phase-one-summary-panel {
        margin-bottom: 12px !important;
    }

    .phase-one-summary-panel {
        margin-top: 12px !important;
    }
}



/* =========================================================
   VIEW.PHP — HORIZONTAL SPACING ONLY
   Match the wider Research/Researcher page spacing.
   No logic, workflow, buttons, uploads, or data changed.
========================================================= */

.page-content {
    padding-left: 28px !important;
    padding-right: 28px !important;
}

.research-view {
    width: 100% !important;
    max-width: none !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

@media (max-width: 760px) {
    .page-content {
        padding-left: 14px !important;
        padding-right: 14px !important;
    }
}


/* Inline preview: shows supported linked document contents immediately. */
.rems-embedded-preview {
    background: #f8fbf9;
}
.rems-embedded-preview iframe {
    display: block;
    width: 100%;
    height: min(74vh, 840px);
    min-height: 480px;
    border: 0;
    background: #fff;
}
.rems-embedded-preview-help {
    margin: 0;
    padding: 12px 17px;
    border-top: 1px solid #dcebe1;
    color: #64756b;
    font-size: 12px;
    text-align: center;
}
.rems-embedded-preview-help a {
    color: #066b33;
    font-weight: 700;
}
@media (max-width: 760px) {
    .rems-embedded-preview iframe { height: 66vh; min-height: 350px; }
}

/* Review confirmation: scrollable in-page preview without changing RPS workflow. */
.rems-review-scroll {
    max-height: min(67vh, 670px);
    min-height: 290px;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
    background: #f7fbf8;
    border-bottom: 1px solid #dbe8df;
}
.rems-review-scroll .rems-embedded-preview iframe,
.rems-review-scroll .research-document-frame {
    width: 100%;
    height: 1100px;
    min-height: 1100px;
    display: block;
}
.rems-review-scroll .rems-embedded-preview-help {
    background: #f8fbf9;
}

/* External document review: a clean Visit Link with the checkbox directly below. */
.rems-review-scroll:has(.rems-external-visit) {
    max-height: none;
    min-height: 0;
    overflow: visible;
    scrollbar-gutter: auto;
}
.rems-external-visit {
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f7fbf8;
    padding: 30px 24px 26px;
}
.rems-external-visit-content {
    max-width: 650px;
    width: 100%;
    text-align: center;
}
.rems-external-visit-icon {
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    margin: 0 auto 14px;
    border-radius: 15px;
    color: #087b37;
    background: #e5f3e9;
    font-size: 25px;
}
.rems-external-visit-content h3 {
    margin: 0 0 8px;
    color: #153f29;
    font-size: 19px;
    font-weight: 800;
}
.rems-external-visit-content p {
    margin: 0 auto 18px;
    max-width: 570px;
    color: #627369;
    font-size: 13px;
    line-height: 1.65;
}
.rems-visit-document-button { padding: 11px 28px; }
.rems-visit-status {
    margin: 15px auto 0;
    max-width: 590px;
    color: #557260;
    font-size: 12px;
    line-height: 1.5;
}
.rems-external-visit-content .rems-visit-status { margin-bottom: 0; }
.rems-review-confirm-form:has(#documentReviewAttested) {
    border-top: 1px solid #dbe8df;
}
@media (max-width: 760px) {
    .rems-external-visit { padding: 24px 15px; }
}
.rems-review-end {
    padding: 23px 14px;
    text-align: center;
    color: #076b31;
    background: #eaf5ec;
    font-size: 13px;
    font-weight: 700;
}
.rems-review-end i { margin-right: 7px; }
.rems-review-confirm-form { padding: 18px 22px 17px; background: #fff; }
.rems-review-acknowledge { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; font-weight: 650; color: #1d402d; cursor: pointer; }
.rems-review-acknowledge input { width: 17px; height: 17px; margin-top: 1px; accent-color: #087b37; }
.rems-review-acknowledge:has(input:disabled) { opacity: .55; cursor: not-allowed; }
.rems-review-instruction { margin: 9px 0 0; color: #637469; font-size: 12px; line-height: 1.55; }
.rems-review-unavailable { padding: 15px 20px; background: #fff9ec; }
.rems-confirm-success { padding: 12px 20px; color: #086b31; background: #e6f6e9; font-size: 13px; }
.rems-confirm-error { padding: 12px 20px; color: #963838; background: #fff0ee; font-size: 13px; }
.research-document-viewer-actions button[disabled] { opacity: .52; cursor: not-allowed; box-shadow: none; }
.rems-confirmed-button { pointer-events: none; }
.rems-original-fallback { color: #087b37; font-size: 13px; font-weight: 650; }
@media (max-width: 760px) { .rems-review-scroll { max-height: 58vh; } .rems-review-confirm-form { padding: 16px; } }


/* Self-initiated links: same eye-only control, disabled submit and note as Project Monitoring. */
.rems-document-link-row .rems-link-preview {
    min-width: 46px;
    width: 46px;
    min-height: 43px;
    font-size: 18px;
}
.rems-document-link-row .rems-link-preview.is-viewed {
    color: #005c26 !important;
    border-color: #007a33 !important;
    background: #edf8f0 !important;
}
.upe-upload-form button[type="submit"]:disabled {
    opacity: .48 !important;
    cursor: not-allowed !important;
    box-shadow: none !important;
}
.rems-upload-view-note {
    margin: 5px 0 9px;
    font-size: 10px;
    color: #7b877d;
    line-height: 1.5;
}
.rems-upload-view-note.is-viewed { color: #007a33; font-weight: 700; }
.rems-upload-view-note.is-error { color: #a83b3b; }
/* Link-only upload fields: uniform, compact across every research step. */
.upe-upload-form { gap: 10px; }
.upe-upload-item { padding: 14px 16px; }
.rems-document-link-row { display: flex; align-items: stretch; gap: 9px; min-width: 0; }
.rems-document-link-input { flex: 1 1 auto; min-width: 0; height: 43px; border-radius: 10px; font-size: 13px; }
.rems-document-link-row .rems-link-preview { flex: 0 0 auto; min-width: 76px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 12px; }
.rems-link-preview.disabled { opacity: .5; pointer-events: none; }
.rems-external-viewer { padding: 40px 22px; text-align: center; background: #f7fbf8; border: 1px solid #dcebe1; border-radius: 14px; }
.rems-external-viewer > i { font-size: 36px; color: #087b37; }
.rems-external-viewer h3 { font-size: 20px; font-weight: 800; margin: 12px 0 8px; }
.rems-external-viewer p { max-width: 570px; margin: 0 auto 20px; color: #65756a; }
@media (max-width: 500px) { .rems-document-link-row { flex-wrap: wrap; } .rems-document-link-row .rems-link-preview { min-height: 42px; } }

/* Regular research completion: keep the check icon and text together. */
.research-final-completion.rems-regular-completion > .workflow-content {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 12px !important;
}
.research-final-completion.rems-regular-completion .workflow-text {
    flex: 1 1 auto;
    min-width: 0;
    margin-left: 0 !important;
    text-align: left !important;
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


/* UPE Certificate 1 & 2: match the clean outlined eye-button hover. */
.upe-complete-file .upe-certificate-eye-btn,
.upe-complete-file .upe-certificate-eye-btn:hover,
.upe-complete-file .upe-certificate-eye-btn:focus,
.upe-complete-file .upe-certificate-eye-btn:active {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 38px !important;
    height: 36px !important;
    padding: 0 !important;
    border: 1px solid #00863c !important;
    border-radius: 9px !important;
    background: #f4faf6 !important;
    color: #00863c !important;
    box-shadow: none !important;
    transform: none !important;
}
.upe-complete-file .upe-certificate-eye-btn .bi-eye {
    color: inherit !important;
    font-size: 17px !important;
    line-height: 1 !important;
}


/* Document viewer: confirmation follows the acknowledgment. */
.rems-review-confirm-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
}
.rems-review-confirm-actions button:disabled {
    opacity: .52;
    cursor: not-allowed;
    box-shadow: none;
}
.rems-review-confirmed-status {
    display: flex;
    justify-content: flex-end;
    padding: 14px 20px;
    background: #fff;
}

/* Small next-step guidance on the NTP card (Faculty and RPS). */
.workflow-card.ntp-issued-card .ntp-next-step-note {
    margin-top: 8px !important;
    margin-bottom: 0 !important;
    font-size: 10px !important;
    line-height: 1.45 !important;
    color: #687a6d !important;
    text-align: left !important;
}

.workflow-card.ntp-issued-card .ntp-next-step-note .bi {
    font-size: 11px;
    margin-right: 4px;
}

html[data-rems-theme="dark"] .workflow-card.ntp-issued-card .ntp-next-step-note {
    color: #c0d3c2 !important;
}

/* NTP buttons: keep their original position on the right, just slightly lower. */
.workflow-card.ntp-issued-card .workflow-content {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 16px !important;
}

.workflow-card.ntp-issued-card .workflow-text {
    min-width: 0 !important;
}

.workflow-card.ntp-issued-card .ntp-generated-actions {
    width: auto !important;
    min-width: 360px !important;
    margin-top: 14px !important;
    flex-shrink: 0 !important;
    justify-content: flex-end !important;
    gap: 12px !important;
}

.workflow-card.ntp-issued-card .ntp-generated-actions .btn {
    padding-left: 18px !important;
    padding-right: 18px !important;
    gap: 9px !important;
}

@media (max-width: 1100px) {
    .workflow-card.ntp-issued-card .workflow-content {
        flex-direction: column !important;
        align-items: flex-start !important;
    }
    .workflow-card.ntp-issued-card .ntp-generated-actions {
        width: 100% !important;
        min-width: 0 !important;
        margin-top: 10px !important;
        justify-content: flex-start !important;
    }
}

@media (max-width: 620px) {
    .workflow-card.ntp-issued-card .ntp-generated-actions {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .workflow-card.ntp-issued-card .ntp-generated-actions .btn {
        width: 100% !important;
        min-width: 0 !important;
    }
}

/* UREC/IACUC certificate font ONLY — retain original design and content. */
.urec-modern-page .urec-modern-sheet,
.urec-modern-page .urec-modern-sheet *:not(.bi),
.urec-modern-page .generated-certificate-toolbar,
.urec-modern-page .generated-certificate-toolbar *:not(.bi) {
    font-family: "Open Sans", Arial, sans-serif !important;
}


/* =========================================================
   ASU-REMS VIEW DETAILS — DARK MODE CONTRAST FIX
   Theme only. Existing review workflow, permissions, links,
   validation, document confirmation and PDF markup untouched.
   The white printable certificate and permit sheets stay white.
========================================================= */
html[data-rems-theme="dark"] .main-page {
    background: #0b1d15 !important;
    color: #eaf3eb !important;
}
html[data-rems-theme="dark"] .main-page > .page-content {
    background: #0b1d15 !important;
    color: #eaf3eb !important;
}

/* Shared topbar on research/view.php */
html[data-rems-theme="dark"] .asu-dashboard-topbar.rems-uniform-topbar {
    background: #11291e !important;
    border-bottom-color: #365744 !important;
    box-shadow: 0 7px 24px rgba(0,0,0,.16) !important;
}
html[data-rems-theme="dark"] .asu-dashboard-topbar .asu-dashboard-brandline strong,
html[data-rems-theme="dark"] .asu-dashboard-topbar .dashboard-user-copy strong {
    color: #f0f7f0 !important;
}
html[data-rems-theme="dark"] .asu-dashboard-topbar .asu-dashboard-brandline span,
html[data-rems-theme="dark"] .asu-dashboard-topbar .dashboard-user-copy span {
    color: #bfcfc1 !important;
}
html[data-rems-theme="dark"] .asu-dashboard-topbar .sidebar-toggle {
    color: #edf5ed !important;
}

/* Green module banner and gold Back button retain approved ASU branding. */
html[data-rems-theme="dark"] .research-view-heading h1 {
    color: #ffffff !important;
}
html[data-rems-theme="dark"] .research-view-heading p {
    color: #e1f1e1 !important;
}
html[data-rems-theme="dark"] .research-view-heading > .back-to-research-new-proposal-style,
html[data-rems-theme="dark"] .research-view-heading > .back-to-research-new-proposal-style i {
    color: #003f1a !important;
}

/* Summary / manuscript title / progress (previously hardcoded to white). */
html[data-rems-theme="dark"] .research-view .research-summary {
    background: linear-gradient(180deg,#183629,#122b20) !important;
    color: #edf6ed !important;
    border-color: #3c604b !important;
    box-shadow: 0 14px 30px rgba(0,0,0,.14) !important;
}
html[data-rems-theme="dark"] .research-view .research-summary h2 {
    color: #f4faf3 !important;
}
html[data-rems-theme="dark"] .research-view .research-summary-meta,
html[data-rems-theme="dark"] .research-view .research-summary-meta span,
html[data-rems-theme="dark"] .research-view .research-progress-head,
html[data-rems-theme="dark"] .research-view .research-progress-head small,
html[data-rems-theme="dark"] .research-view .research-progress-subtitle {
    color: #c5d8c8 !important;
}
html[data-rems-theme="dark"] .research-view .research-summary-meta i,
html[data-rems-theme="dark"] .research-view .research-summary .research-progress-head strong {
    color: #87e0a6 !important;
}
html[data-rems-theme="dark"] .research-view .research-summary .research-progress,
html[data-rems-theme="dark"] .research-view .research-summary .workflow-status-strip {
    border-top-color: #3d604a !important;
}
html[data-rems-theme="dark"] .research-view .research-summary .research-progress-bar {
    background: #355945 !important;
}
html[data-rems-theme="dark"] .research-view .research-summary .research-progress-value {
    background: linear-gradient(90deg,#1da35a,#64d68e) !important;
}
/* Preserve intentional gold status chip in both themes. */
html[data-rems-theme="dark"] .research-view .research-summary .research-status {
    background: #ffd51f !important;
    color: #003f1a !important;
    border-color: #ffd51f !important;
}

/* Main information, content, documents, history and RPS workflow cards. */
html[data-rems-theme="dark"] .research-view :is(
    .research-status-overview,.research-panel,.workflow-card,
    .research-document-viewer,.phase-one-summary-panel
) {
    background: #152f23 !important;
    color: #eaf3ea !important;
    border-color: #3b5d48 !important;
    box-shadow: 0 11px 28px rgba(0,0,0,.12) !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .research-panel-header,.research-document-viewer-header
) {
    background: #1c3b2c !important;
    color: #f2f8f2 !important;
    border-color: #43664f !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .research-panel-body,.research-document-viewer-actions,
    .workflow-content,.research-document-viewer-title
) {
    color: #eaf3ea !important;
    border-color: #395c47 !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .research-panel-title,.research-status-overview h3,
    .content-block h4,.workflow-text h4,
    .document-info strong,.info-item strong,
    .timeline-item strong,.evaluation-item strong,
    .phase-one-text strong,.research-document-viewer-title strong,
    .upe-upload-head h5,.upe-complete-file strong
) {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .research-status-label,.research-status-overview p,
    .info-item label,.info-item span,
    .content-block p,.document-info small,
    .timeline-item small,.evaluation-item small,
    .timeline-item p,.evaluation-item p,
    .workflow-text p,.phase-one-text small,
    .research-document-viewer-title small,
    .upe-upload-head p,.upe-complete-file small,
    .empty-data,.form-text,.form-note,.small-label,
    .research-field-note,.section-comment-modal-note
) {
    color: #c0d3c2 !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .research-panel-icon,.research-status-overview-icon,
    .phase-one-icon,.document-icon,.document-open-icon
) {
    background: #294b38 !important;
    color: #8be0a5 !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .document-item,.phase-one-item,.upe-upload-item,
    .self-next-step,.research-preview-message,
    .research-preview-message-box
) {
    background: #1b3a2b !important;
    color: #e9f3ea !important;
    border-color: #43684f !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .document-item-link:hover,.document-item-link:focus-visible,
    .phase-one-item.phase-one-clickable:hover,
    .phase-one-item.phase-one-clickable:focus-visible
) {
    background: #274b37 !important;
    color: #f4faf4 !important;
    border-color: #69a87c !important;
}
html[data-rems-theme="dark"] .research-view .phase-one-item.phase-one-clickable,
html[data-rems-theme="dark"] .research-view .phase-one-item.phase-one-clickable:visited {
    color: #eaf3ea !important;
}
html[data-rems-theme="dark"] .research-view .phase-one-item .phase-one-view-label,
html[data-rems-theme="dark"] .research-view .section-comment-button,
html[data-rems-theme="dark"] .research-view .workflow-inline-link {
    color: #9ce0af !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .phase-one-item.is-complete,.upe-upload-item.is-complete,
    .self-next-step.is-ready
) {
    background: #214b34 !important;
    border-color: #4f8a61 !important;
    color: #e2f6e4 !important;
}
html[data-rems-theme="dark"] .research-view .phase-one-item.is-missing {
    background: #423522 !important;
    border-color: #866d43 !important;
}
html[data-rems-theme="dark"] .research-view .phase-one-item.is-missing .phase-one-icon {
    background: #624927 !important;
    color: #ffe0a3 !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .upe-upload-status,.upe-upload-item.is-complete .upe-upload-status
) {
    background: #2d5b40 !important;
    color: #d4f3dc !important;
}
html[data-rems-theme="dark"] .research-view .workflow-card.is-warning,
html[data-rems-theme="dark"] .research-view .workflow-notice {
    background: #3d3523 !important;
    color: #ffe4ae !important;
    border-color: #866e43 !important;
}
html[data-rems-theme="dark"] .research-view .workflow-notice :is(p,strong,span) {
    color: #ffe4ae !important;
}

/* All editable fields, dropdown options, and outline actions. */
html[data-rems-theme="dark"] .research-view :is(
    input:not([type="checkbox"]):not([type="radio"]),
    select,textarea,.form-control,.form-select,.input-group-text
) {
    background-color: #203f2f !important;
    color: #f0f7ee !important;
    border-color: #51745a !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] .research-view :is(input,textarea)::placeholder {
    color: #bdd0be !important;
    opacity: 1;
}
html[data-rems-theme="dark"] .research-view :is(input,textarea,select):focus {
    background-color: #284d39 !important;
    border-color: #8ac99c !important;
    box-shadow: 0 0 0 3px rgba(106,192,135,.15) !important;
}
html[data-rems-theme="dark"] .research-view select option {
    background: #203f2f;
    color: #f0f7ee;
}
html[data-rems-theme="dark"] .research-view :is(
    .form-label,.admin-review-form .form-label,.info-item label
) {
    color: #d7e8da !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .btn-outline-asu,.btn-outline-secondary,.btn-self-initiated,
    .rems-link-preview,.rems-document-link-row .rems-link-preview
) {
    background: #224632 !important;
    color: #eaf7ed !important;
    border-color: #6a9273 !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .btn-outline-asu,.btn-outline-secondary,.btn-self-initiated,
    .rems-link-preview
):not(:disabled):hover {
    background: #315940 !important;
    color: #fff !important;
}
/* Top banner's gold Back button takes precedence over generic outlines. */
html[data-rems-theme="dark"] .research-view .research-view-heading > .btn.back-to-research-new-proposal-style {
    background: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #003f1a !important;
}
html[data-rems-theme="dark"] .research-view .research-view-heading > .btn.back-to-research-new-proposal-style:hover {
    background: #f0bd00 !important;
}

/* Comments and Bootstrap dialogs used by Faculty and RPS. */
html[data-rems-theme="dark"] :is(
    #submitModal,#resubmitModal,#rpsSectionCommentModal,#sectionRemarkViewModal
) :is(.modal-content,.modal-body) {
    background: #162f23 !important;
    border-color: #42664f !important;
    color: #eaf3ea !important;
}
html[data-rems-theme="dark"] :is(
    #submitModal,#resubmitModal,#rpsSectionCommentModal,#sectionRemarkViewModal
) :is(.modal-header,.modal-footer) {
    background: #1d3b2c !important;
    border-color: #42664f !important;
    color: #edf7ed !important;
}
html[data-rems-theme="dark"] :is(
    #submitModal,#resubmitModal,#rpsSectionCommentModal,#sectionRemarkViewModal
) :is(.modal-title,.modal-body h4,.modal-body h5,.form-label,p,.section-comment-view-message) {
    color: #edf6ed !important;
}
html[data-rems-theme="dark"] :is(
    #submitModal,#resubmitModal,#rpsSectionCommentModal,#sectionRemarkViewModal
) :is(.section-comment-modal-note,.section-comment-view-message,.alert-warning) {
    background: #254735 !important;
    color: #d9ebdc !important;
    border-color: #52735b !important;
}
html[data-rems-theme="dark"] #rpsSectionCommentText {
    background: #213f30 !important;
    color: #edf7ed !important;
    border-color: #54745d !important;
}
html[data-rems-theme="dark"] :is(
    #submitModal,#resubmitModal,#rpsSectionCommentModal,#sectionRemarkViewModal
) .btn-outline-secondary {
    background: #274735 !important;
    color: #eff7ee !important;
    border-color: #668a70 !important;
}

/* External document review is themed; the actual file preview remains unchanged. */
html[data-rems-theme="dark"] .research-view :is(
    .rems-external-visit,.rems-external-viewer,
    .rems-review-scroll,.rems-review-confirm-form,
    .rems-review-confirmed-status,.rems-embedded-preview,
    .rems-review-scroll .rems-embedded-preview-help,
    .research-preview-message,.research-preview-message-box
) {
    background: #1b3b2b !important;
    color: #e8f4e8 !important;
    border-color: #3d6149 !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .rems-external-visit-content h3,.research-preview-message-box h3
) {
    color: #f0f8ef !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .rems-external-visit-content p,.rems-visit-status,
    .rems-review-instruction,.rems-review-acknowledge,
    .rems-embedded-preview-help,.research-preview-message-box p,
    .rems-upload-view-note
) {
    color: #c8dacb !important;
}
html[data-rems-theme="dark"] .research-view :is(
    .rems-external-visit-icon,.research-preview-message-icon
) {
    background: #28523a !important;
    color: #8be5ab !important;
}
html[data-rems-theme="dark"] .research-view .rems-review-end,
html[data-rems-theme="dark"] .research-view .rems-confirm-success {
    background: #224b34 !important;
    color: #b9f4c8 !important;
}
html[data-rems-theme="dark"] .research-view .rems-confirm-error,
html[data-rems-theme="dark"] .research-view .research-errors {
    background: #4b2929 !important;
    border-color: #8a5252 !important;
    color: #ffe1e1 !important;
}
html[data-rems-theme="dark"] .research-view .rems-review-unavailable {
    background: #483c23 !important;
    color: #ffe2a4 !important;
}
html[data-rems-theme="dark"] .research-view .rems-upload-view-note.is-viewed,
html[data-rems-theme="dark"] .research-view .rems-upload-info-note::before {
    color: #8de6a9 !important;
}
/* Leave document pages, DOCX paper previews, UREC certificates and permits
   in their established light print format. Never recolor document content. */
/* URRC + In-House: consistent two-button layout on EVERY revision round.
   No lock icon. Submit stays disabled until a new saved document exists for
   the current review decision, and resets for any subsequent revision. */
.workflow-buttons.inhouse-matched-revision-buttons {
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
}
.workflow-buttons.inhouse-matched-revision-buttons > form {
    margin: 0 !important;
}
.workflow-buttons.inhouse-matched-revision-buttons .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    gap: 4px;
}
.workflow-buttons.inhouse-matched-revision-buttons .btn:disabled {
    box-shadow: none !important;
}


/* RPS revision verification: subtle text link, never an extra action button. */
.rems-revision-inline-link-row {
    margin: 0 0 14px;
}
.rems-revision-inline-link,
.rems-revision-inline-link:visited {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #007a33 !important;
    font-size: 12px;
    font-weight: 800;
    text-decoration: underline !important;
    text-underline-offset: 2px;
}
.rems-revision-inline-link:hover,
.rems-revision-inline-link:focus-visible {
    color: #005c26 !important;
}
.rems-revision-inline-link:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 3px;
    border-radius: 2px;
}
html[data-rems-theme="dark"] .rems-revision-inline-link,
html[data-rems-theme="dark"] .rems-revision-inline-link:visited {
    color: #99e7ae !important;
}
html[data-rems-theme="dark"] .rems-revision-inline-link:hover,
html[data-rems-theme="dark"] .rems-revision-inline-link:focus-visible {
    color: #c6f6cf !important;
}



/* =========================================================
   ASU-REMS — CERTIFICATE / PERMIT FOOTER BOUNDARY REPAIR
   Only changes document layout; no statuses, data or actions.
   Let the sheet grow with content on screen so generated fields
   never render below its green/gold frame.
========================================================= */
@media screen {
    .generated-certificate-page .urec-modern-sheet {
        aspect-ratio: auto !important;
        height: auto !important;
        min-height: 1120px !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }

    .generated-certificate-page .urec-modern-inner {
        height: auto !important;
        min-height: 1060px !important;
        box-sizing: border-box !important;
        padding-bottom: 42px !important;
    }

    .generated-certificate-page .urec-modern-document-footer {
        flex-shrink: 0 !important;
        margin-top: 24px !important;
        padding-top: 12px !important;
    }
}

/* Keep generated information INSIDE the physical A4 print border. */
@media print {
    @page { size: A4 portrait; margin: 10mm; }

    .generated-certificate-page .urec-modern-sheet {
        aspect-ratio: auto !important;
        width: 190mm !important;
        height: 274mm !important;
        min-height: 0 !important;
        padding: 6mm !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
        break-inside: avoid !important;
    }

    .generated-certificate-page .urec-modern-inner {
        height: 100% !important;
        min-height: 0 !important;
        padding: 10mm 11mm 9mm !important;
        box-sizing: border-box !important;
    }

    .generated-certificate-page .urec-modern-header {
        margin-bottom: 15px !important;
    }

    .generated-certificate-page .urec-modern-title {
        margin-bottom: 16px !important;
    }

    .generated-certificate-page .urec-modern-title h3 {
        font-size: 32px !important;
    }

    .generated-certificate-page .urec-modern-body {
        font-size: 10px !important;
        line-height: 1.55 !important;
    }

    .generated-certificate-page .urec-modern-body p {
        margin-bottom: 9px !important;
    }

    .generated-certificate-page .urec-modern-research-title {
        margin: 5px auto 12px !important;
        padding-bottom: 8px !important;
        font-size: 16px !important;
        line-height: 1.35 !important;
    }

    .generated-certificate-page .urec-modern-classification {
        margin: 5px auto 7px !important;
        padding: 6px 15px !important;
        font-size: 18px !important;
    }

    .generated-certificate-page .urec-modern-description {
        margin-bottom: 8px !important;
        line-height: 1.45 !important;
    }

    .generated-certificate-page .urec-modern-details {
        margin-top: 14px !important;
        padding-top: 9px !important;
    }

    .generated-certificate-page .urec-modern-detail {
        padding: 4px 0 !important;
    }

    .generated-certificate-page .urec-modern-document-footer {
        flex-shrink: 0 !important;
        margin-top: auto !important;
        padding-top: 12px !important;
    }

    .generated-certificate-page .urec-modern-system-note {
        padding-top: 7px !important;
        font-size: 8px !important;
        line-height: 1.4 !important;
    }

    .generated-certificate-page .urec-modern-generated {
        margin-top: 5px !important;
        font-size: 7.5px !important;
        line-height: 1.35 !important;
    }
}


/* Requested: matching, comfortable eye-only button sizes in Research View. */
.research-view .rems-document-link-row > a.rems-link-preview,
.research-view .upe-complete-file > a.upe-certificate-eye-btn {
    box-sizing: border-box !important;
    flex: 0 0 52px !important;
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
.research-view .rems-document-link-row > a.rems-link-preview > i.bi-eye,
.research-view .upe-complete-file > a.upe-certificate-eye-btn > i.bi-eye {
    font-size: 19px !important;
    line-height: 1 !important;
}

/* =========================================================
   CERTIFICATES — OFFICIAL RESEARCH AND PUBLICATION HEADER
   AND AUTHORIZED SIGNATORY LINE
   Applies only to the four generated research documents.
========================================================= */
.generated-certificate-page .urec-modern-header .urec-modern-office {
    margin: 7px 0 0 !important;
    color: #354b3a !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    letter-spacing: .02em !important;
}
.generated-certificate-page .urec-modern-header .urec-modern-location {
    margin: 4px 0 0 !important;
    font-size: 10.5px !important;
    font-weight: 600 !important;
}
.generated-certificate-page .urec-modern-signatory {
    position: relative !important;
    z-index: 2 !important;
    width: min(100%, 300px) !important;
    margin: 14px auto 16px !important;
    padding-top: 20px !important;
    text-align: center !important;
    break-inside: avoid !important;
}
.generated-certificate-page .urec-modern-signature-line {
    width: 250px !important;
    max-width: 100% !important;
    height: 0 !important;
    margin: 0 auto 7px !important;
    border-top: 1.5px solid #28392e !important;
}
.generated-certificate-page .urec-modern-signature-role {
    color: #223328 !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    line-height: 1.5 !important;
}
@media print {
    .generated-certificate-page .urec-modern-signatory {
        margin: 8px auto 9px !important;
        padding-top: 9px !important;
    }
    .generated-certificate-page .urec-modern-signature-line {
        width: 58mm !important;
        border-top-width: 1px !important;
        margin-bottom: 4px !important;
    }
    .generated-certificate-page .urec-modern-signature-role {
        font-size: 10px !important;
    }
    .generated-certificate-page .urec-modern-header .urec-modern-office {
        font-size: 9px !important;
    }
    .generated-certificate-page .urec-modern-header .urec-modern-location {
        font-size: 9px !important;
    }
}


/* =========================================================
   ASU-REMS | SIMPLE RPS COMMENT VIEWER
   Minimal UI-only override. No changes to saved comments or workflow.
========================================================= */
#sectionRemarkViewModal .modal-dialog {
    width: calc(100% - 28px) !important;
    max-width: 445px !important;
    margin: 16px auto !important;
}
#sectionRemarkViewModal .modal-content {
    overflow: hidden !important;
    border: 1px solid #e1e8e3 !important;
    border-left: 3px solid #087a3b !important;
    border-top: 1px solid #e1e8e3 !important;
    border-radius: 14px !important;
    background: #ffffff !important;
    box-shadow: 0 14px 38px rgba(18, 43, 27, .14) !important;
}
#sectionRemarkViewModal .modal-content::before {
    display: none !important;
    content: none !important;
}
#sectionRemarkViewModal .modal-header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    padding: 20px 22px 12px !important;
    border: 0 !important;
    background: #ffffff !important;
}
#sectionRemarkViewModal .modal-title {
    color: #173925 !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 17px !important;
    font-weight: 700 !important;
    line-height: 1.35 !important;
}
#sectionRemarkViewModal .rems-comment-subtitle {
    display: block;
    margin-top: 4px;
    color: #7b867d;
    font-size: 11px;
    line-height: 1.4;
}
#sectionRemarkViewModal .rems-comment-private {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #849288;
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
}
#sectionRemarkViewModal .modal-body {
    padding: 4px 22px 12px !important;
    background: #ffffff !important;
}
#sectionRemarkViewModal .section-comment-view-message {
    display: block !important;
    min-height: 0 !important;
    max-height: 36vh !important;
    overflow-y: auto !important;
    overflow-wrap: anywhere !important;
    margin: 0 !important;
    padding: 14px 15px !important;
    border: 1px solid #e5ebe6 !important;
    border-radius: 10px !important;
    background: #f8faf8 !important;
    color: #28372c !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 12px !important;
    font-weight: 400 !important;
    line-height: 1.7 !important;
    white-space: pre-wrap !important;
}
#sectionRemarkViewModal .modal-footer {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex-wrap: wrap !important;
    gap: 12px !important;
    padding: 11px 22px 18px !important;
    border: 0 !important;
    background: #ffffff !important;
}
#sectionRemarkViewModal .section-comment-view-meta {
    display: flex !important;
    align-items: flex-start !important;
    flex: 1 1 195px !important;
    flex-direction: column !important;
    gap: 4px !important;
    margin: 0 !important;
    color: #526958 !important;
}
#sectionRemarkViewModal #sectionRemarkViewAuthorWrap {
    color: #2d4434 !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    line-height: 1.4 !important;
    overflow-wrap: anywhere !important;
}
#sectionRemarkViewModal #sectionRemarkViewDateWrap {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #87938a !important;
    font-size: 9.5px !important;
    line-height: 1.5 !important;
}
#sectionRemarkViewModal .modal-footer .btn {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 95px !important;
    min-height: 37px !important;
    margin: 0 !important;
    padding: 8px 18px !important;
    border: 1px solid #087a3b !important;
    border-radius: 9px !important;
    background: #087a3b !important;
    color: #ffffff !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    box-shadow: none !important;
}
#sectionRemarkViewModal .modal-footer .btn:hover {
    border-color: #00652e !important;
    background: #00652e !important;
    color: #ffffff !important;
}
#sectionRemarkViewModal .modal-footer .btn:focus-visible {
    outline: 2px solid #087a3b;
    outline-offset: 2px;
}
html[data-rems-theme="dark"] #sectionRemarkViewModal .modal-content,
html[data-rems-theme="dark"] #sectionRemarkViewModal .modal-header,
html[data-rems-theme="dark"] #sectionRemarkViewModal .modal-body,
html[data-rems-theme="dark"] #sectionRemarkViewModal .modal-footer {
    background: #1d3828 !important;
    border-color: #476350 !important;
}
html[data-rems-theme="dark"] #sectionRemarkViewModal .modal-content {
    border-left-color: #83c59c !important;
}
html[data-rems-theme="dark"] #sectionRemarkViewModal .modal-title,
html[data-rems-theme="dark"] #sectionRemarkViewModal #sectionRemarkViewAuthorWrap {
    color: #f0f6f0 !important;
}
html[data-rems-theme="dark"] #sectionRemarkViewModal .rems-comment-subtitle,
html[data-rems-theme="dark"] #sectionRemarkViewModal .rems-comment-private,
html[data-rems-theme="dark"] #sectionRemarkViewModal #sectionRemarkViewDateWrap {
    color: #bdd0c1 !important;
}
html[data-rems-theme="dark"] #sectionRemarkViewModal .section-comment-view-message {
    background: #294735 !important;
    color: #eff8ef !important;
    border-color: #4b6553 !important;
}
html[data-rems-theme="dark"] #sectionRemarkViewModal .modal-footer .btn {
    border-color: #148f4d !important;
    background: #148f4d !important;
    color: #ffffff !important;
}
html[data-rems-theme="dark"] #sectionRemarkViewModal .modal-footer .btn:hover {
    border-color: #0e763d !important;
    background: #0e763d !important;
    color: #ffffff !important;
}
@media (max-width: 576px) {
    #sectionRemarkViewModal .modal-dialog {
        width: calc(100vw - 24px) !important;
        max-width: calc(100vw - 24px) !important;
        margin: 12px auto !important;
    }
    #sectionRemarkViewModal .modal-header { padding: 17px 17px 10px !important; }
    #sectionRemarkViewModal .modal-body { padding: 3px 17px 10px !important; }
    #sectionRemarkViewModal .modal-footer { padding: 10px 17px 16px !important; }
    #sectionRemarkViewModal .modal-footer .btn { flex: 0 0 auto !important; }
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


<div class="research-view">


<?php if (
    $isGeneratedEthicsCertificateView
): ?>


<!-- =====================================================
     SYSTEM-GENERATED UREC / IACUC CERTIFICATE
====================================================== -->

<div class="generated-certificate-page urec-modern-page">


<div class="generated-certificate-toolbar">


<div>

<h1>
UREC / IACUC Certificate
</h1>

<p>
Official system-generated research review certification.
</p>

</div>


<div class="generated-certificate-actions">


<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>"
    class="btn btn-outline-asu"
>

<i class="bi bi-arrow-left me-1"></i>

Back to Research Details

</a>


<button
    type="button"
    class="btn btn-asu"
    onclick="window.print()"
>

<i class="bi bi-printer me-1"></i>

Print / Save as PDF

</button>


</div>


</div>


<section class="generated-certificate-sheet urec-modern-sheet">


<div class="urec-modern-top-shape"></div>

<div class="urec-modern-bottom-shape"></div>


<div class="urec-modern-inner">


<header class="urec-modern-header">

<p class="urec-modern-republic">
Republic of the Philippines
</p>

<h2>
Aklan State University
</h2>

<p class="urec-modern-office">
Research and Publication Services
</p>

<p class="urec-modern-location">
Banga, Aklan
</p>

</header>


<div class="urec-modern-title">

<h3>
Certificate
</h3>

<span>
UREC / IACUC Review Classification
</span>

</div>


<div class="urec-modern-body">


<p class="urec-modern-intro">
This is to certify that the research manuscript entitled
</p>


<div class="urec-modern-research-title">

&ldquo;<?= e($research['research_title'] ?? 'Untitled Research'); ?>&rdquo;

</div>


<p>

submitted by
<strong>
<?= e(trim(($research['first_name'] ?? '') . ' ' . ($research['middle_name'] ?? '') . ' ' . ($research['last_name'] ?? ''))); ?>
</strong>,
with Manuscript No.
<strong>
<?= e($research['manuscript_no'] ?: 'Not assigned'); ?>
</strong>,
has been reviewed by the University Research Ethics Committee /
Institutional Animal Care and Use Committee through the ASU Research and
Extension Management System.

</p>


<p class="urec-modern-classification-label">

Recorded Review Classification

</p>


<div class="urec-modern-classification">

<?= e(ethicsResultLabel($latestEthicsReview['result'] ?? null)); ?>

</div>


<p class="urec-modern-description">

<?= e(ethicsClassificationDescription($latestEthicsReview['result'] ?? null)); ?>

</p>


<p>

This certification is issued for documentation and research processing purposes
within Aklan State University.

</p>


</div>


<div class="urec-modern-details">


<div class="urec-modern-detail">

<span>
Remarks
</span>

<strong>

<?= e(
    !empty($latestEthicsReview['remarks'])
        ? $latestEthicsReview['remarks']
        : 'No remarks recorded.'
); ?>

</strong>

</div>


<div class="urec-modern-detail">

<span>
Date Recorded
</span>

<strong>

<?php if (!empty($latestEthicsReview['reviewed_at'])): ?>

<?= e(
    date(
        'F d, Y',
        strtotime($latestEthicsReview['reviewed_at'])
    )
); ?>

<?php else: ?>

Not recorded

<?php endif; ?>

</strong>

</div>


<div class="urec-modern-detail">

<span>
Certificate No.
</span>

<strong>

<?= e($generatedEthicsCertificateNumber); ?>

</strong>

</div>


<div class="urec-modern-detail">

<span>
Funding Source
</span>

<strong>

<?= e(
    $research['funding_source']
        ?: 'Not specified'
); ?>

</strong>

</div>


<div class="urec-modern-detail">

<span>
Recorded By
</span>

<strong>

<?= e(
    trim(
        ($latestEthicsReview['first_name'] ?? '')
        . ' '
        . ($latestEthicsReview['middle_name'] ?? '')
        . ' '
        . ($latestEthicsReview['last_name'] ?? '')
    )
    ?: 'Research Processing Staff'
); ?>

</strong>

</div>


</div>


<div class="urec-modern-document-footer">


<div class="urec-modern-signatory">
<div class="urec-modern-signature-line" aria-hidden="true"></div>
<div class="urec-modern-signature-role">Chairperson</div>
</div>

<div class="urec-modern-system-note">

This certification was generated through the ASU Research &amp; Extension
Management System. The signature line above is for the authorized signatory.

</div>


<div class="urec-modern-generated">

<p>

<strong>
Generated on:
</strong>

<?= e(date('F d, Y h:i A')); ?>

</p>


<p>

<strong>
Generated by:
</strong>

Research &amp; Extension Management System

</p>


</div>


</div>


</div>


</section>


<footer class="content-footer">

<span>

© <?= date('Y'); ?>
Aklan State University

</span>

<span class="footer-divider">
•
</span>

<span>

Research &amp; Extension Management System

</span>

</footer>


</div>


<?php elseif (
    $isGeneratedNoticeToProceedView
): ?>


<!-- =====================================================
     SYSTEM-GENERATED NOTICE TO PROCEED
====================================================== -->

<div class="generated-certificate-page urec-modern-page">


<div class="generated-certificate-toolbar">


<div>

<h1>
Notice to Proceed
</h1>

<p>
Official system-generated authorization for approved research implementation.
</p>

</div>


<div class="generated-certificate-actions">


<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>"
    class="btn btn-outline-asu"
>

<i class="bi bi-arrow-left me-1"></i>

Back to Research Details

</a>


<button
    type="button"
    class="btn btn-asu"
    onclick="window.print()"
>

<i class="bi bi-printer me-1"></i>

Print / Save as PDF

</button>


</div>


</div>


<section class="generated-certificate-sheet urec-modern-sheet">


<div class="urec-modern-top-shape"></div>

<div class="urec-modern-bottom-shape"></div>


<div class="urec-modern-inner">


<header class="urec-modern-header">

<p class="urec-modern-republic">
Republic of the Philippines
</p>

<h2>
Aklan State University
</h2>

<p class="urec-modern-office">
Research and Publication Services
</p>

<p class="urec-modern-location">
Banga, Aklan
</p>

</header>


<div class="urec-modern-title">

<h3>
Certificate
</h3>

<span>
Notice to Proceed
</span>

</div>


<div class="urec-modern-body">


<p class="urec-modern-intro">
This is to formally notify that the approved research project entitled
</p>


<div class="urec-modern-research-title">

&ldquo;<?= e($research['research_title'] ?? 'Untitled Research'); ?>&rdquo;

</div>


<p>
submitted by
<strong><?= e($researcherName ?: 'Researcher'); ?></strong>,
with Manuscript No.
<strong><?= e($research['manuscript_no'] ?: 'Not assigned'); ?></strong>,
has completed the required research processing and review steps recorded in the
ASU Research and Extension Management System.
</p>


<p>
By this Notice to Proceed, the researcher is authorized to begin the
implementation of the approved research project, subject to applicable university
policies, monitoring requirements, and submission of required progress and
terminal reports.
</p>


<p class="urec-modern-classification-label">
Recorded Authorization
</p>


<div class="urec-modern-classification">
NOTICE TO PROCEED
</div>


<p class="urec-modern-description">
This document serves as the official system-generated go-signal that the research
may move from review processing to implementation and project monitoring.
</p>


<p>
This certification is issued for documentation, implementation, and monitoring
purposes within Aklan State University.
</p>


</div>


<div class="urec-modern-details">


<div class="urec-modern-detail">
<span>Certificate No.</span>
<strong><?= e($generatedNoticeToProceedNumber); ?></strong>
</div>

<div class="urec-modern-detail">
<span>Researcher</span>
<strong><?= e($researcherName ?: 'Researcher'); ?></strong>
</div>

<div class="urec-modern-detail">
<span>Funding Source</span>
<strong><?= e($research['funding_source'] ?: 'Not specified'); ?></strong>
</div>

<div class="urec-modern-detail">
<span>Status</span>
<strong>Ongoing</strong>
</div>

<div class="urec-modern-detail">
<span>Date Recorded</span>
<strong><?= e(date('F d, Y', strtotime($noticeToProceedDateRecorded))); ?></strong>
</div>

<div class="urec-modern-detail">
<span>Recorded By</span>
<strong><?= e($noticeToProceedRecordedBy); ?></strong>
</div>


</div>


<div class="urec-modern-document-footer">


<div class="urec-modern-signatory">
<div class="urec-modern-signature-line" aria-hidden="true"></div>
<div class="urec-modern-signature-role">Director, RPS</div>
</div>

<div class="urec-modern-system-note">
This Notice to Proceed was generated through the ASU Research &amp; Extension
Management System. The signature line above is for the authorized signatory.
</div>


<div class="urec-modern-generated">

<p>
<strong>Generated on:</strong>
<?= e(date('F d, Y h:i A')); ?>
</p>

<p>
<strong>Generated by:</strong>
Research &amp; Extension Management System
</p>

</div>


</div>


</div>


</section>


<footer class="content-footer">

<span>
© <?= date('Y'); ?>
Aklan State University
</span>

<span class="footer-divider">
•
</span>

<span>
Research &amp; Extension Management System
</span>

</footer>


</div>


<?php elseif (
    $isGeneratedSelfInitiatedPermitView
): ?>


<!-- =====================================================
     SYSTEM-GENERATED SELF-INITIATED PERMIT
====================================================== -->

<div class="generated-certificate-page urec-modern-page">


<div class="generated-certificate-toolbar">

<div>
<h1>
Permit to Publish / Present
</h1>
<p>
Automatically generated after the Self-Initiated Terminal Report was submitted.
</p>
</div>

<div class="generated-certificate-actions">
<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>"
    class="btn btn-outline-asu"
>
<i class="bi bi-arrow-left me-1"></i>
Back to Research Details
</a>

<button
    type="button"
    class="btn btn-asu"
    onclick="window.print()"
>
<i class="bi bi-printer me-1"></i>
Print / Save as PDF
</button>
</div>

</div>

<section class="generated-certificate-sheet urec-modern-sheet">
<div class="urec-modern-top-shape"></div>
<div class="urec-modern-bottom-shape"></div>

<div class="urec-modern-inner">

<header class="urec-modern-header">
<p class="urec-modern-republic">Republic of the Philippines</p>
<h2>Aklan State University</h2>
<p class="urec-modern-office">Research and Publication Services</p>
<p class="urec-modern-location">Banga, Aklan</p>
</header>

<div class="urec-modern-title">
<h3>Permit</h3>
<span>Permit to Publish / Present</span>
</div>

<div class="urec-modern-body">
<p class="urec-modern-intro">
This is to certify that the Self-Initiated research project entitled
</p>

<div class="urec-modern-research-title">
&ldquo;<?= e($research['research_title'] ?? 'Untitled Research'); ?>&rdquo;
</div>

<p>
conducted by
<strong><?= e($researcherName ?: 'Researcher'); ?></strong>,
with Manuscript No.
<strong><?= e($research['manuscript_no'] ?: 'Not assigned'); ?></strong>,
has submitted the required University Pool of Experts certificates,
completed research / whole manuscript, and Terminal Report through the
ASU Research and Extension Management System.
</p>

<p>
In accordance with the Self-Initiated research workflow, the system hereby
generates this <strong>Permit to Publish / Present</strong>. The researcher may
proceed with publication or presentation of the completed research, subject to
applicable university policies, authorship and acknowledgment requirements,
intellectual property rules, and any requirements of the receiving journal,
publisher, conference, or presentation venue.
</p>

<p class="urec-modern-classification-label">Recorded Permit Type</p>
<div class="urec-modern-classification">PERMIT TO PUBLISH / PRESENT</div>
<p class="urec-modern-description">
This permit was generated automatically after the required Self-Initiated
Terminal Report was submitted in ASU-REMS.
</p>
</div>

<div class="urec-modern-details">
<div class="urec-modern-detail"><span>Permit No.</span><strong><?= e($selfInitiatedPermitNumber); ?></strong></div>
<div class="urec-modern-detail"><span>Researcher</span><strong><?= e($researcherName ?: 'Researcher'); ?></strong></div>
<div class="urec-modern-detail"><span>Manuscript No.</span><strong><?= e($research['manuscript_no'] ?: 'Not assigned'); ?></strong></div>
<div class="urec-modern-detail"><span>Track</span><strong>Self-Initiated</strong></div>
<div class="urec-modern-detail"><span>Date Issued</span><strong><?= e(date('F d, Y', strtotime($selfInitiatedPermitIssuedAt))); ?></strong></div>
<div class="urec-modern-detail"><span>Generated By</span><strong>ASU Research &amp; Extension Management System</strong></div>
</div>

<div class="urec-modern-document-footer">
<div class="urec-modern-signatory">
<div class="urec-modern-signature-line" aria-hidden="true"></div>
<div class="urec-modern-signature-role">Director, RPS</div>
</div>

<div class="urec-modern-system-note">
This permit was generated through the ASU Research and Extension Management
System. The signature line above is for the authorized signatory.
</div>
<div class="urec-modern-generated">
<p><strong>Generated on:</strong> <?= e(date('F d, Y h:i A')); ?></p>
<p><strong>Generated by:</strong> Research &amp; Extension Management System</p>
</div>
</div>

</div>
</section>

<footer class="content-footer">
<span>© <?= date('Y'); ?> Aklan State University</span>
<span class="footer-divider">•</span>
<span>Research &amp; Extension Management System</span>
</footer>

</div>


<?php elseif (
    $isGeneratedPermitView
): ?>


<!-- =====================================================
     SYSTEM-GENERATED PUBLICATION / PRESENTATION PERMIT
====================================================== -->

<div class="generated-certificate-page urec-modern-page">

<div class="generated-certificate-toolbar">
<div>
<h1>
Permit for <?= e($permitTypeLabel); ?>
</h1>
<p>
Official system-generated research permit issued after Terminal Report review.
</p>
</div>

<div class="generated-certificate-actions">
<a href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>" class="btn btn-outline-asu">
<i class="bi bi-arrow-left me-1"></i>
Back to Research Details
</a>
<button type="button" class="btn btn-asu" onclick="window.print()">
<i class="bi bi-printer me-1"></i>
Print / Save as PDF
</button>
</div>
</div>

<section class="generated-certificate-sheet urec-modern-sheet">
<div class="urec-modern-top-shape"></div>
<div class="urec-modern-bottom-shape"></div>
<div class="urec-modern-inner">

<header class="urec-modern-header">
<p class="urec-modern-republic">Republic of the Philippines</p>
<h2>Aklan State University</h2>
<p class="urec-modern-office">Research and Publication Services</p>
<p class="urec-modern-location">Banga, Aklan</p>
</header>

<div class="urec-modern-title">
<h3>Permit</h3>
<span>Permit for <?= e($permitTypeLabel); ?></span>
</div>

<div class="urec-modern-body">
<p class="urec-modern-intro">
This is to certify that the research project entitled
</p>
<div class="urec-modern-research-title">
&ldquo;<?= e($research['research_title'] ?? 'Untitled Research'); ?>&rdquo;
</div>
<p>
conducted by
<strong><?= e($researcherName ?: 'Researcher'); ?></strong>,
with Manuscript No.
<strong><?= e($research['manuscript_no'] ?: 'Not assigned'); ?></strong>,
has completed the Terminal Report submission requirement recorded in the
ASU Research and Extension Management System.
</p>
<p>
Following review of the submitted Terminal Report, the Research Office hereby
grants this <strong>Permit for <?= e($permitTypeLabel); ?></strong>. The researcher
may proceed with the <?= e(strtolower($permitTypeLabel)); ?> of findings, outputs,
or materials arising from the approved research, subject to applicable university
policies, proper authorship and acknowledgment, intellectual property rules, and
requirements of the receiving journal, publisher, conference, or venue.
</p>
<p class="urec-modern-classification-label">Recorded Permit Type</p>
<div class="urec-modern-classification">PERMIT FOR <?= e(strtoupper($permitTypeLabel)); ?></div>
<p class="urec-modern-description">
This permit confirms Research Office clearance at the Terminal Report stage.
It does not replace any additional approval that may be required by an external
publisher, conference organizer, ethics body, funding agency, or intellectual
property authority.
</p>
<?php if ($permitRemarksDisplay !== ''): ?>
<p class="urec-modern-description">
<strong>RPS Remarks:</strong> <?= nl2br(e($permitRemarksDisplay)); ?>
</p>
<?php endif; ?>
</div>

<div class="urec-modern-details">
<div class="urec-modern-detail"><span>Permit No.</span><strong><?= e($generatedPermitNumber); ?></strong></div>
<div class="urec-modern-detail"><span>Permit Type</span><strong><?= e($permitTypeLabel); ?></strong></div>
<div class="urec-modern-detail"><span>Researcher</span><strong><?= e($researcherName ?: 'Researcher'); ?></strong></div>
<div class="urec-modern-detail"><span>Manuscript No.</span><strong><?= e($research['manuscript_no'] ?: 'Not assigned'); ?></strong></div>
<div class="urec-modern-detail"><span>Date Issued</span><strong><?= e(date('F d, Y', strtotime($permitIssuedDateRecorded))); ?></strong></div>
<div class="urec-modern-detail"><span>Issued By</span><strong><?= e($permitIssuedBy); ?></strong></div>
</div>

<div class="urec-modern-document-footer">
<div class="urec-modern-signatory">
<div class="urec-modern-signature-line" aria-hidden="true"></div>
<div class="urec-modern-signature-role">Director, RPS</div>
</div>

<div class="urec-modern-system-note">
This permit was generated through the ASU Research and Extension Management
System. The signature line above is for the authorized signatory.
</div>
<div class="urec-modern-generated">
<p><strong>Generated on:</strong> <?= e(date('F d, Y h:i A')); ?></p>
<p><strong>Generated by:</strong> Research &amp; Extension Management System</p>
</div>
</div>

</div>
</section>

<footer class="content-footer">
<span>© <?= date('Y'); ?> Aklan State University</span>
<span class="footer-divider">•</span>
<span>Research &amp; Extension Management System</span>
</footer>

</div>

<?php elseif (
    $viewerDocument
): ?>


<!-- =====================================================
     DOCUMENT VIEWER
====================================================== -->

<div class="research-view-heading">


<div>


<h1>

Document Viewer

</h1>


<p>

Open the saved research document link, or preview a previously uploaded file.

</p>


</div>


<div class="workflow-buttons">


<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>"
    class="btn btn-outline-asu"
>

<i class="bi bi-arrow-left me-1"></i>

Back to Research Details

</a>





</div>


</div>


<section class="research-document-viewer">


<div class="research-document-viewer-header">


<div class="research-document-viewer-title">


<div class="document-icon">


<?php if ($viewerExternalUrl !== null): ?>
<i class="bi bi-link-45deg"></i>
<?php elseif (
    $viewerExtension === 'pdf'
): ?>

<i class="bi bi-file-earmark-pdf"></i>

<?php elseif (
    in_array(
        $viewerExtension,
        [
            'jpg',
            'jpeg',
            'png'
        ],
        true
    )
): ?>

<i class="bi bi-file-earmark-image"></i>

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

<?= $viewerExternalUrl ? 'External link' : e(researchFileSize($viewerDocument['file_size'] !== null ? (int)$viewerDocument['file_size'] : null)); ?>

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





</div>



<div id="researchDocumentReviewScroll" class="rems-review-scroll" tabindex="0" aria-label="Scrollable document review area">
<?php if ($viewerExternalUrl !== null): ?>
<div class="rems-external-visit">
    <div class="rems-external-visit-content">
        <div class="rems-external-visit-icon"><i class="bi bi-link-45deg" aria-hidden="true"></i></div>
        <h3>Visit Document Link</h3>
        <p>Open the submitted document in a new tab to review its contents.</p>
        <a id="documentVisitLink"
           class="btn btn-asu rems-visit-document-button"
           href="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>&amp;document_id=<?= (int)$viewerDocument['document_id']; ?>&amp;file_mode=visit"
           target="_blank" rel="noopener noreferrer">
            <i class="bi bi-box-arrow-up-right me-2"></i>Visit Link
        </a>
    </div>
</div>
<?php elseif (
    $viewerExtension === 'pdf'
): ?>


<iframe
    src="<?= e($viewerInlineUrl); ?>"
    class="research-document-frame"
    title="<?= e(
        $viewerDocument[
            'original_name'
        ]
    ); ?>"
></iframe>


<?php elseif (
    in_array(
        $viewerExtension,
        [
            'jpg',
            'jpeg',
            'png'
        ],
        true
    )
): ?>


<div class="research-image-preview">

<img
    src="<?= e($viewerInlineUrl); ?>"
    alt="<?= e($viewerDocument['original_name']); ?>"
>

</div>


<?php elseif (
    $viewerExtension === 'docx'
    &&
    $viewerPreviewText !== null
): ?>


<div class="research-docx-preview">


<div class="research-docx-page">

<?= e(
    $viewerPreviewText
); ?>

</div>


</div>


<?php else: ?>


<div class="research-preview-message">


<div class="research-preview-message-box">


<div class="research-preview-message-icon">

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
    <?= $viewerExternalUrl ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
    class="btn btn-asu"
>

<i class="bi bi-download me-1"></i>

Download Document

</a>


</div>


</div>


<?php endif; ?>

<?php if ($viewerReviewable && $viewerExternalUrl === null): ?>
<div id="documentReviewEnd" class="rems-review-end">
    <i class="bi bi-check-circle"></i> End of review area
</div>
<?php endif; ?>
</div><!-- /researchDocumentReviewScroll -->

<?php if ($viewerAlreadyConfirmed): ?>
<div class="rems-review-confirmed-status">
    <span class="btn btn-asu btn-sm rems-confirmed-button" aria-label="You have confirmed reviewing this document">
        <i class="bi bi-check-circle-fill me-1"></i> Confirmed
    </span>
</div>
<?php endif; ?>

<?php if (isset($_GET['review_confirmed']) && $viewerAlreadyConfirmed): ?>
<div class="rems-confirm-success"><i class="bi bi-check-circle-fill"></i> Document review confirmed and saved.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="rems-confirm-error" role="alert"><?= e(implode(' ', $errors)); ?></div>
<?php endif; ?>

<?php if ($viewerReviewable && !$viewerAlreadyConfirmed): ?>
<form id="documentConfirmForm" method="POST"
      action="<?= BASE_URL; ?>/research/view.php?id=<?= $researchId; ?>&amp;document_id=<?= (int)$viewerDocument['document_id']; ?>&amp;file_mode=view"
      class="rems-review-confirm-form">
    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
    <input type="hidden" name="action" value="confirm_document_review">
    <label class="rems-review-acknowledge">
        <input type="checkbox" name="review_attested" id="documentReviewAttested" value="1" disabled>
        <span>I have checked the document, including its contents through the last page.</span>
    </label>
    <div class="rems-review-confirm-actions">
        <button
            id="documentConfirmButton"
            type="submit"
            class="btn btn-asu btn-sm"
            disabled
            title="Review the document to the bottom and acknowledge it first"
        >
        Confirm
        </button>
    </div>

</form>
<?php elseif (!$viewerReviewable): ?>
<p class="rems-review-instruction rems-review-unavailable">
    Confirmation is unavailable because this older uploaded file cannot be previewed here.
    Open the document using the available download option.
</p>
<?php endif; ?>

</section>

<?php if ($viewerReviewable && !$viewerAlreadyConfirmed): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const reviewArea = document.getElementById('researchDocumentReviewScroll');
    const acknowledgement = document.getElementById('documentReviewAttested');
    const confirmButton = document.getElementById('documentConfirmButton');
    const docFrame = reviewArea && reviewArea.querySelector('iframe');
    const visitLink = document.getElementById('documentVisitLink');
    const isExternal = Boolean(visitLink);
    let visitVerified = <?= $viewerExternalUrl !== null
        ? ($viewerExternalVisitRecorded ? 'true' : 'false') : 'true'; ?>;
    let previewLoaded = !docFrame;

    if (!reviewArea || !acknowledgement || !confirmButton) return;

    function checkReviewBottom() {
        const reachedBottom = (reviewArea.scrollTop + reviewArea.clientHeight
            >= reviewArea.scrollHeight - 22);
        const scrollWasUsed = reviewArea.scrollHeight <= reviewArea.clientHeight + 4
            || reviewArea.scrollTop > 24;
        // External pages cannot expose their scroll position to ASU-REMS.
        // Require the server-recorded Visit Link action and the reviewer's
        // explicit acknowledgment instead. Local files still require scrolling.
        const readyForAttestation = isExternal
            ? visitVerified
            : (previewLoaded && reachedBottom && scrollWasUsed);

        acknowledgement.disabled = !readyForAttestation;
        if (!readyForAttestation) acknowledgement.checked = false;
        confirmButton.disabled = !(readyForAttestation && acknowledgement.checked);


    }

    if (docFrame) {
        docFrame.addEventListener('load', function () {
            previewLoaded = true;
            checkReviewBottom();
        });
    }

    async function refreshVisitStatus() {
        if (!isExternal) return;
        try {
            const response = await fetch(
                <?= json_encode(BASE_URL . '/research/view.php?id=' . $researchId .
                    '&document_id=' . (int)$viewerDocument['document_id'] . '&file_mode=visit_status'); ?>,
                { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } }
            );
            if (!response.ok) return;
            const state = await response.json();
            visitVerified = state.visited === true;
            checkReviewBottom();
        } catch (err) {
            // Network errors do not unlock confirmation.
        }
    }

    if (isExternal) {
        // Visit Link opens a new browser tab. Check the server-recorded visit
        // when the reviewer returns to this ASU-REMS tab.
        window.addEventListener('focus', refreshVisitStatus);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshVisitStatus();
        });
        visitLink.addEventListener('click', function () {
            setTimeout(refreshVisitStatus, 1100);
        });
        refreshVisitStatus();
    }

    reviewArea.addEventListener('scroll', checkReviewBottom, { passive: true });
    acknowledgement.addEventListener('change', checkReviewBottom);
    checkReviewBottom();
});
</script>
<?php endif; ?>


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


<?php else: ?>


<!-- =====================================================
     HEADING
====================================================== -->

<div class="research-view-heading">


<div>


<h1>

Research Manuscript Details

</h1>


<p>

View the manuscript number, funding source, uploaded files,
evaluation results and current project status.

</p>


</div>



<a
    href="<?= BASE_URL; ?>/research/index.php"
    class="btn btn-outline-asu back-to-research-new-proposal-style"
>

<i class="bi bi-arrow-left me-1"></i>

Back to Research

</a>


</div>



<!-- =====================================================
     ERRORS — display only unsuccessful actions, never success flashes.
====================================================== -->

<?php if (
    is_array($flash)
    && in_array(strtolower((string)($flash['type'] ?? '')), ['danger', 'error'], true)
): ?>
<div class="research-errors" role="alert">
    <strong>Action not completed. Please check the submitted information.</strong>
    <p class="mb-0 mt-2"><?= e((string)($flash['message'] ?? 'Please review your submission and try again.')); ?></p>
</div>
<?php endif; ?>

<?php if ($visibleErrors): ?>


<div class="research-errors" role="alert">


<strong>

Submission unsuccessful. Please check the submitted details.

</strong>


<ul class="mb-0 mt-2">


<?php foreach (
    $visibleErrors
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



<!-- =====================================================
     RESEARCH SUMMARY
====================================================== -->

<section class="research-summary">


<div class="research-summary-top">


<div>


<h2>

<?= e(
    $research[
        'research_title'
    ]
); ?>

</h2>



<div class="research-summary-meta">


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



<span>

<i class="bi bi-calendar3 me-1"></i>

Created

<?= e(
    date(
        'M d, Y',
        strtotime(
            $research[
                'created_at'
            ]
        )
    )
); ?>

</span>



<?php if (
    !empty(
        $research[
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
            $research[
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
        research-status
        <?= e(
            $statusClass
        ); ?>
    "
>

<?= e(
    $research[
        'status'
    ]
); ?>

</span>


</div>



<!-- =====================================================
     WORKFLOW / IMPLEMENTATION STATUS STRIP
====================================================== -->

<div
    class="research-progress workflow-status-strip <?= $showImplementationProgress ? 'is-progress-mode' : 'is-step-mode'; ?>"
>


<div class="research-progress-head">


<div>


<span>

<?= e(
    $workflowDisplay[
        'label'
    ]
); ?>

</span>


<small class="research-progress-subtitle">

<?= e(
    $workflowDisplay[
        'description'
    ]
); ?>

</small>


</div>


<strong>

<?= e(
    $workflowBadgeText
); ?>

</strong>


</div>


<?php if ($showImplementationProgress): ?>


<div class="research-progress-bar">


<div
    class="research-progress-value"
    style="width: <?= $progressPercentage; ?>%;"
></div>


</div>


<?php endif; ?>


</div>
</section>




<?php
/*
 * Only the two Phase 1 summary tiles are affected.
 * HTTPS document links open the original document directly in a new tab.
 * Existing uploaded files continue to use the authenticated viewer.
 */
$phaseOneDocumentCardUrl = static function (?array $document) use ($researchId): ?string {
    if (!$document || (int)($document['document_id'] ?? 0) <= 0) {
        return null;
    }

    $storedPath = (string)($document['file_path'] ?? '');
    if (researchExternalDocumentUrl($storedPath) !== null
        || str_starts_with($storedPath, 'uploads/research/')) {
        return BASE_URL
            . '/research/view.php?id=' . (int)$researchId
            . '&document_id=' . (int)$document['document_id']
            . '&file_mode=view';
    }

    return null;
};

// Keep document review available inside the existing manuscript summary tile.
// No separate "View Revised Manuscript" button is added to the action panel.
$manuscriptSummaryDocument = $proposalDocument;
if (($user['role'] ?? '') === 'rps'
    && ($research['workflow_status'] ?? '') === 'INHOUSE_REVISED_SUBMITTED'
    && $latestInhouseRevisedDocument !== null) {
    $manuscriptSummaryDocument = $latestInhouseRevisedDocument;
}
$manuscriptSummaryUrl = $phaseOneDocumentCardUrl($manuscriptSummaryDocument);
?>

<!-- =====================================================
     PHASE 1 MANUSCRIPT UPLOAD SUMMARY
====================================================== -->

<section class="research-panel phase-one-summary-panel">


<div class="research-panel-header">


<h2 class="research-panel-title">


<span class="research-panel-icon">

<i class="bi bi-upload"></i>

</span>

Manuscript Upload Summary

</h2>


</div>


<div class="research-panel-body">


<div class="phase-one-grid">


<div class="phase-one-item <?= !empty($research['manuscript_no']) ? 'is-complete' : 'is-missing'; ?>">

<span class="phase-one-icon">
<i class="bi bi-hash"></i>
</span>

<div class="phase-one-text">
<small>Manuscript No.</small>
<strong>
<?= e($research['manuscript_no'] ?: 'Not assigned yet'); ?>
</strong>
<span>System-generated tracking number.</span>
</div>

</div>


<div class="phase-one-item <?= !empty($research['funding_source']) ? 'is-complete' : 'is-missing'; ?>">

<span class="phase-one-icon">
<i class="bi bi-wallet2"></i>
</span>

<div class="phase-one-text">
<small>Funding Source</small>
<strong>
<?= e($research['funding_source'] ?: 'Not specified'); ?>
</strong>
<span>Required before RPS processing.</span>
</div>

</div>


<?php if ($manuscriptSummaryUrl !== null): ?>
<a class="phase-one-item is-complete phase-one-clickable"
   href="<?= e($manuscriptSummaryUrl); ?>"
   target="_blank" rel="noopener noreferrer"
   aria-label="View manuscript: <?= e((string)$manuscriptSummaryDocument['original_name']); ?>">
<?php else: ?>
<div class="phase-one-item <?= $hasProposalDocument ? 'is-complete' : 'is-missing'; ?>">
<?php endif; ?>

<span class="phase-one-icon"><i class="bi bi-file-earmark-text"></i></span>
<div class="phase-one-text">
    <small><?= ($manuscriptSummaryDocument !== $proposalDocument) ? 'Revised Manuscript File' : 'Manuscript File'; ?></small>
    <strong><?= $manuscriptSummaryDocument
        ? e($manuscriptSummaryDocument['original_name']) : 'Missing manuscript'; ?></strong>
    <?php if ($manuscriptSummaryUrl !== null): ?>
        <span class="phase-one-view-label"><i class="bi bi-box-arrow-up-right"></i> View Manuscript</span>
    <?php else: ?>
        <span><?= $hasProposalDocument ? 'Link unavailable.' : 'Upload required.'; ?></span>
    <?php endif; ?>
</div>

<?php if ($manuscriptSummaryUrl !== null): ?></a><?php else: ?></div><?php endif; ?>



</div>


</div>


</section>


<!-- =====================================================
     MAIN GRID
====================================================== -->

<?php
/*
 * Research details display cleanup only.
 * Hide the redundant summary boxes for:
 * - Faculty owner (Regular / For URRC and Self-Initiated / UPE)
 * - RPS account
 *
 * This changes presentation only. Workflow actions, statuses,
 * deadlines, archive rules, remarks, and RPS processing logic are untouched.
 */
$hideRedundantResearchPanels =
    (
        ($user['role'] ?? '') === 'faculty'
        &&
        (int)($research['researcher_id'] ?? 0) === (int)($user['user_id'] ?? 0)
    )
    ||
    ($user['role'] ?? '') === 'rps';
?>

<div class="research-grid<?= $hideRedundantResearchPanels ? ' research-clean-grid' : ''; ?>">


<!-- =====================================================
     LEFT
====================================================== -->

<div class="research-column">


<!-- RESEARCH INFORMATION -->

<section class="research-panel">


<div class="research-panel-header">


<h2 class="research-panel-title">


<span class="research-panel-icon">

<i class="bi bi-journal-text"></i>

</span>

Research Information

</h2>


</div>



<div class="research-panel-body">


<div class="info-grid">


<div class="info-item">

<label>
Research Category
</label>

<strong>

<?= e(
    $research[
        'research_category'
    ]
    ?: 'Not specified'
); ?>

</strong>

</div>



<div class="info-item">

<label>
Research Type
</label>

<strong>

<?= e(
    $research[
        'research_type'
    ]
    ?: 'Not specified'
); ?>

</strong>

</div>



<div class="info-item">

<label>
Manuscript Number
</label>

<strong>

<?= e(
    $research[
        'manuscript_no'
    ]
    ?: 'Assigned upon submission'
); ?>

</strong>

</div>



<div class="info-item">

<label>
Submission Track
</label>

<strong>

<?= e(
    ($research['workflow_status'] ?? '') === 'AWAITING_PATHWAY_SELECTION'
        ? 'To Be Selected'
        : (($research['submission_track'] ?? '') === 'SELF_INITIATED'
            ? 'Self-Initiated' : 'For URRC / Regular')
); ?>

</strong>

</div>



<div class="info-item">

<label>
Workflow Stage
</label>

<strong>

<?= e(
    $research[
        'workflow_stage'
    ]
    ?: 'SUBMISSION'
); ?>

</strong>

</div>



<div class="info-item">

<label>
Workflow Status
</label>

<strong>

<?= e(
    $research[
        'workflow_status'
    ]
    ?: 'DRAFT'
); ?>

</strong>

</div>



<div class="info-item">

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



<div class="info-item">

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


</div>


</div>


</section>



<!-- RESEARCH CONTENT -->

<section class="research-panel" id="research-content">


<div class="research-panel-header">


<h2 class="research-panel-title">


<span class="research-panel-icon">

<i class="bi bi-file-text"></i>

</span>

Research Content

</h2>


</div>



<div class="research-panel-body">


<div class="content-block">

<h4>
Abstract
</h4>

<p><?= e(
    $research['abstract']
); ?></p>

<?php
renderResearchSectionRemarkBox(
    $user,
    $research,
    $sectionRemarks,
    'abstract',
    'Abstract'
);
?>

</div>



<div class="content-block">

<h4>
Objectives
</h4>

<p><?= e(
    $research['objectives']
); ?></p>

<?php
renderResearchSectionRemarkBox(
    $user,
    $research,
    $sectionRemarks,
    'objectives',
    'Objectives'
);
?>

</div>



<div class="content-block">

<h4>
Methodology
</h4>

<p><?= e(
    $research['methodology']
); ?></p>

<?php
renderResearchSectionRemarkBox(
    $user,
    $research,
    $sectionRemarks,
    'methodology',
    'Methodology'
);
?>

</div>



<div class="content-block">

<h4>
Expected Output
</h4>

<p><?= e(
    $research[
        'expected_output'
    ]
); ?></p>

<?php
renderResearchSectionRemarkBox(
    $user,
    $research,
    $sectionRemarks,
    'expected_output',
    'Expected Output'
);
?>

</div>


</div>


</section>



<!-- FUNDING -->

<section class="research-panel">


<div class="research-panel-header">


<h2 class="research-panel-title">


<span class="research-panel-icon">

<i class="bi bi-calendar3"></i>

</span>

Funding & Schedule

</h2>


</div>



<div class="research-panel-body">


<div class="info-grid">


<div class="info-item">

<label>
Funding Source
</label>

<strong>

<?= e(
    $research[
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
    $research[
        'estimated_budget'
    ]
    !== null
): ?>

₱<?= number_format(
    (float)$research[
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



<div class="info-item">

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



<?php if (!$hideRedundantResearchPanels): ?>

<!-- EVALUATION HISTORY -->

<section class="research-panel">


<div class="research-panel-header">


<h2 class="research-panel-title">


<span class="research-panel-icon">

<i class="bi bi-clipboard-check"></i>

</span>

Evaluation History

</h2>


</div>



<div class="research-panel-body">


<?php if (
    empty(
        $evaluations
    )
): ?>


<div class="empty-data">

No evaluation records yet.

</div>


<?php else: ?>


<?php foreach (
    $evaluations
    as
    $evaluation
): ?>


<?php

$evaluatorName =
    trim(
        $evaluation[
            'first_name'
        ]
        .
        ' '
        .
        (
            !empty(
                $evaluation[
                    'middle_name'
                ]
            )
                ? $evaluation[
                    'middle_name'
                ]
                . ' '
                : ''
        )
        .
        $evaluation[
            'last_name'
        ]
    );

?>


<div class="evaluation-item">


<strong>

<?= e(
    $evaluation[
        'decision'
    ]
); ?>

</strong>


<small>

<?= e(
    $evaluatorName
); ?>

·

<?= e(
    date(
        'M d, Y h:i A',
        strtotime(
            $evaluation[
                'evaluated_at'
            ]
        )
    )
); ?>

</small>


<?php if (
    !empty(
        $evaluation[
            'remarks'
        ]
    )
): ?>


<p>

<?= e(
    $evaluation[
        'remarks'
    ]
); ?>

</p>


<?php endif; ?>


</div>


<?php endforeach; ?>


<?php endif; ?>


</div>


</section>

<?php endif; ?>


</div>



<!-- =====================================================
     RIGHT
====================================================== -->

<?php if (!$hideRedundantResearchPanels): ?>

<div class="research-column">


<!-- RESEARCHER -->

<section class="research-panel">


<div class="research-panel-header">


<h2 class="research-panel-title">


<span class="research-panel-icon">

<i class="bi bi-person"></i>

</span>

Lead Researcher

</h2>


</div>



<div class="research-panel-body">


<strong
    style="
        font-size:11px;
        display:block;
        margin-bottom:4px;
    "
>

<?= e(
    $researcherName
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
    $research[
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

Faculty / Researcher

</span>


</div>


</section>



<!-- DOCUMENTS -->

<section class="research-panel">


<div class="research-panel-header">


<h2 class="research-panel-title">


<span class="research-panel-icon">

<i class="bi bi-paperclip"></i>

</span>

Documents

</h2>


<small class="text-muted">

<?= count(
    $documents
); ?>

</small>


</div>



<div class="research-panel-body">


<?php if (
    empty(
        $documents
    )
): ?>


<div class="empty-data">

No research documents uploaded yet.

</div>


<?php else: ?>


<div class="document-list">


<?php foreach (
    $documents
    as
    $document
): ?>

<?php

$documentExtension =
    strtolower(
        (string)$document['file_extension']
    );

$documentUrl =
    BASE_URL .
    '/research/view.php?id=' .
    $researchId .
    '&document_id=' .
    (int)$document[
        'document_id'
    ] .
    '&file_mode=view';

?>

<a
    href="<?= e($documentUrl); ?>"
    class="document-item document-item-link"
    target="_blank"
    rel="noopener noreferrer"
    title="Open <?= e($document['original_name']); ?>"
>

<div class="document-icon">

<?php if (in_array($documentExtension, ['url','link'], true)): ?>
<i class="bi bi-link-45deg"></i>
<?php elseif (
    $documentExtension === 'pdf'
): ?>

<i class="bi bi-file-earmark-pdf"></i>

<?php elseif (
    in_array(
        $documentExtension,
        [
            'jpg',
            'jpeg',
            'png'
        ],
        true
    )
): ?>

<i class="bi bi-file-earmark-image"></i>

<?php else: ?>

<i class="bi bi-file-earmark-word"></i>

<?php endif; ?>

</div>

<div class="document-info">

<strong>
<?= e($document['original_name']); ?>
</strong>

<small>
<?= e($document['document_type']); ?>
·
<?= in_array($documentExtension, ['url', 'link'], true) ? 'External link' : e(researchFileSize($document['file_size'] !== null ? (int)$document['file_size'] : null)); ?>
<br>
Uploaded
<?= e(
    date(
        'M d, Y',
        strtotime($document['uploaded_at'])
    )
); ?>
</small>

</div>

<div class="document-open-icon" aria-hidden="true">
    <i class="bi bi-box-arrow-up-right"></i>
</div>

</a>

<?php endforeach; ?>


</div>


<?php endif; ?>


</div>


</section>



<!-- STATUS HISTORY -->

<section class="research-panel status-history-panel">


<div class="research-panel-header">


<h2 class="research-panel-title">


<span class="research-panel-icon">

<i class="bi bi-clock-history"></i>

</span>

Status History

</h2>


</div>



<div class="research-panel-body">


<?php if (
    empty(
        $statusHistory
    )
): ?>


<div class="empty-data">

No status history available.

</div>


<?php else: ?>


<?php foreach (
    $statusHistory
    as
    $history
): ?>


<?php

$changedBy =
    trim(
        $history[
            'first_name'
        ]
        .
        ' '
        .
        (
            !empty(
                $history[
                    'middle_name'
                ]
            )
                ? $history[
                    'middle_name'
                ]
                . ' '
                : ''
        )
        .
        $history[
            'last_name'
        ]
    );

?>


<div class="timeline-item">


<strong>

<?= e(
    $history[
        'new_status'
    ]
); ?>

</strong>


<small>

<?= e(
    $changedBy
); ?>

·

<?= e(
    date(
        'M d, Y h:i A',
        strtotime(
            $history[
                'changed_at'
            ]
        )
    )
); ?>

</small>


<?php if (
    !empty(
        $history[
            'remarks'
        ]
    )
): ?>


<p>

<?= e(
    $history[
        'remarks'
    ]
); ?>

</p>


<?php endif; ?>


</div>


<?php endforeach; ?>


<?php endif; ?>


</div>


</section>


</div>

<?php endif; ?>


</div>



<!-- =====================================================
     FACULTY — FINAL REJECTION (ARCHIVED)
     Rejected is final: no revision, review or Self-Initiated continuation.
====================================================== -->
<?php if (
    ($user['role'] ?? '') === 'faculty'
    && (
        in_array((string)($research['workflow_status'] ?? ''),
            ['URRC_REJECTED', 'INHOUSE_FAILED', 'REJECTED', 'ADMIN_REJECTED'], true)
        || ($research['status'] ?? '') === 'Rejected'
    )
): ?>
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>Research Rejected — Archived</h4>
            <p>This research decision is final. The record and original documents
               are retained for institutional audit and reference.</p>
        </div>
        <div class="workflow-buttons">
            <a class="btn btn-asu" href="<?= BASE_URL; ?>/research/create.php">
                <i class="bi bi-plus-circle me-1"></i> Add New Proposal
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     FACULTY DRAFT
====================================================== -->

<?php if (
    $user['role'] === 'faculty'
    &&
    $research['status'] === 'Draft'
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Draft Research Manuscript

</h4>


<p>

You may continue editing this research record.
Once the manuscript is complete,
submit it to the Research Office
for RPS processing.

</p>


<?php if (!$hasRequiredPhaseOneDocuments): ?>


<div class="workflow-notice">

<?php if (!$hasProposalDocument): ?>
Upload a manuscript document through Edit Proposal before submitting.<br>
<?php endif; ?>


</div>


<?php endif; ?>


</div>



<div class="workflow-buttons">


<a
    href="<?= BASE_URL; ?>/research/edit.php?id=<?= $researchId; ?>"
    class="btn btn-outline-asu"
>

<i class="bi bi-pencil-square me-1"></i>

Edit Proposal

</a>


<button
    type="button"
    class="btn btn-asu"
    data-bs-toggle="modal"
    data-bs-target="#submitModal"
    <?= !$hasRequiredPhaseOneDocuments
        ? 'disabled'
        : ''; ?>
>

<i class="bi bi-send me-1"></i>

Submit to RPS Processing

</button>


</div>


</div>


</section>


<?php endif; ?>



<!-- =====================================================
     FACULTY REVISION
====================================================== -->

<?php if (
    $user['role'] === 'faculty'
    &&
    $research['status']
    === 'For Revision'
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Revision Required

</h4>


<p>

<?php if (isDetailedResearchWorkflow($research)): ?>
Review the Research Processing Staff's review
remarks, revise the proposal and upload
the updated research document.
<?php else: ?>
Review the Administrator's evaluation
remarks, revise the proposal and upload
the updated research document.
<?php endif; ?>

</p>


<?php if (($research['workflow_status'] ?? '') === 'INHOUSE_REVISION_REQUIRED'): ?>
<div class="workflow-notice">
    Select <strong>Edit &amp; Save</strong> to save a new In-House revised
    manuscript link. After saving, select <strong>Submit Revision</strong>
    to send it to the RPS In-House Queue.
</div>
<?php elseif (($research['workflow_status'] ?? '') === 'URRC_REVISION_REQUIRED'): ?>
<div class="workflow-notice">
    Select <strong>Edit &amp; Save</strong> to save a new URRC revised
    manuscript link. After saving, select <strong>Submit Revision</strong>
    to send it to the RPS URRC Queue.
</div>
<?php elseif (!$hasRevisedProposalDocument): ?>
<div class="workflow-notice">A revised proposal document has not been uploaded yet.</div>
<?php else: ?>
<div class="workflow-notice">A revised proposal document is available. You may now resubmit for review.</div>
<?php endif; ?>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array((string)($_POST['action'] ?? ''),
        ['submit_urrc_revision', 'submit_inhouse_revision'], true)
    && !empty($errors)): ?>
<div class="workflow-notice" role="alert" style="margin-top:12px;">
    <strong>Revision not submitted.</strong>
    <?php foreach ($errors as $submitMessage): ?>
        <div><?= e($submitMessage); ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div>



<div class="workflow-buttons inhouse-matched-revision-buttons">


<a
    href="<?= BASE_URL; ?>/research/edit.php?id=<?= $researchId; ?>"
    class="btn btn-outline-asu"
>

<i class="bi bi-pencil-square me-1"></i>
Edit &amp; Save
</a>


<?php if (($research['workflow_status'] ?? '') === 'INHOUSE_REVISION_REQUIRED'): ?>
<form method="POST" class="d-inline-block m-0">
    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
    <input type="hidden" name="action" value="submit_inhouse_revision">
    <button type="submit" class="btn btn-asu"
        <?= !$hasCurrentInhouseRevisionDocument ? 'disabled' : ''; ?>
        title="<?= !$hasCurrentInhouseRevisionDocument ? 'Save a new In-House revised link first' : 'Submit revised manuscript to RPS'; ?>">
        <i class="bi bi-send me-1"></i>Submit Revision
    </button>
</form>
<?php elseif (($research['workflow_status'] ?? '') === 'URRC_REVISION_REQUIRED'): ?>
<form method="POST" class="d-inline-block m-0">
    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
    <input type="hidden" name="action" value="submit_urrc_revision">
    <button type="submit" class="btn btn-asu"
        <?= !$hasCurrentUrrcRevisionDocument ? 'disabled' : ''; ?>
        title="<?= !$hasCurrentUrrcRevisionDocument ? 'Save a new URRC revised link first' : 'Submit URRC revised manuscript to RPS'; ?>">
        <i class="bi bi-send me-1"></i>Submit Revision
    </button>
</form>
<?php else: ?>
<button
    type="button"
    class="btn btn-asu"
    data-bs-toggle="modal"
    data-bs-target="#resubmitModal"
    <?= !$canManuallyResubmit ? 'disabled' : ''; ?>
>
    <i class="bi bi-arrow-repeat me-1"></i> Resubmit for Review
</button>
<?php endif; ?>


</div>


</div>


</section>


<?php endif; ?>



<!-- =====================================================
     STEP 2 — FACULTY CHOOSES PATH AFTER SUBMISSION
     Existing ETHICS_COMPLETED records use their legacy pathway section.
====================================================== -->
<?php if (
    ($user['role'] ?? '') === 'faculty'
    && (int)$research['researcher_id'] === (int)$user['user_id']
    && ($research['workflow_status'] ?? '') === 'AWAITING_PATHWAY_SELECTION'
    && ($research['status'] ?? '') === 'Submitted'
): ?>
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>Choose Research Pathway</h4>
            <p>Your proposal is submitted. Choose the correct processing path before any committee review begins.</p>
            <div class="workflow-notice">
                <strong>For URRC:</strong> URRC → In-House → UREC/IACUC → system-generated certificate.<br>
                <strong>Self-Initiated:</strong> 2 UPE Certificates → UREC/IACUC → system-generated certificate.
            </div>
        </div>
        <div class="ethics-choice-actions">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                <input type="hidden" name="action" value="select_path_for_urrc">
                <button type="submit" class="btn btn-asu">
                    <i class="bi bi-arrow-right-circle me-1"></i> For URRC
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                <input type="hidden" name="action" value="select_path_self_initiated">
                <button type="submit" class="btn btn-self-initiated">
                    <i class="bi bi-person-check me-1"></i> Self-Initiated
                </button>
            </form>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- RPS read-only state for newly submitted records awaiting the owner's pathway choice. -->
<?php if (
    ($user['role'] ?? '') === 'rps'
    && ($research['workflow_status'] ?? '') === 'AWAITING_PATHWAY_SELECTION'
): ?>
<section class="workflow-card">
    <div class="workflow-text">
        <h4>Awaiting Faculty Pathway Selection</h4>
        <p>The researcher will select For URRC or Self-Initiated before the appropriate review begins.</p>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     RPS - INITIAL INTAKE OF SELECTED FOR URRC RESEARCH
     This comes BEFORE committee review; existing legacy actions stay intact.
====================================================== -->
<?php if (
    ($user['role'] ?? '') === 'rps'
    && researchUrrcAwaitingRpsIntake($pdo, $researchId, $research)
): ?>
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>New Research Submission — Initial RPS Processing</h4>
            <p>This faculty proposal has selected <strong>For URRC</strong>.
               Review the manuscript link before forwarding it to URRC Review.</p>
            <div class="workflow-notice">
                Next: <strong>URRC Review → In-House Review → UREC/IACUC Review</strong>.
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="forward_to_urrc">
            <button type="submit" class="btn btn-asu">
                <i class="bi bi-arrow-right-circle me-1"></i> Forward to URRC Review
            </button>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     RPS - NEW SUBMISSION
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    &&
    ($research['workflow_status'] ?? '')
    === 'SUBMITTED'
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Initial Research Processing

</h4>


<p>

This research has been submitted by the faculty researcher
and is waiting for initial processing. Start UREC/IACUC
processing to move it to the ethics review queue.

</p>


<div class="workflow-notice">

After starting this step, the research will move from
<strong>New Submissions</strong> to the
<strong>UREC / IACUC</strong> queue.

</div>


</div>


<form method="POST">


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>


<input
    type="hidden"
    name="action"
    value="start_ethics_review"
>


<button
    type="submit"
    class="btn btn-asu"
>

<i class="bi bi-shield-check me-1"></i>

Start UREC / IACUC Processing

</button>


</form>


</div>


</section>


<?php endif; ?>


<!-- =====================================================
     RPS - ETHICS PROCESSING ACTIVE
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    &&
    ($research['workflow_status'] ?? '')
    === 'FOR_ETHICS_REVIEW'
): ?>


<section class="workflow-card">


<div class="workflow-text mb-3">


<h4>

UREC / IACUC Result

</h4>


<p>

Record the official UREC/IACUC classification for this research.
Saving the result will complete the ethics-processing stage and
automatically generate the certificate inside ASU-REMS.

</p>


</div>


<form
    method="POST"
    class="admin-review-form"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>


<input
    type="hidden"
    name="action"
    value="save_ethics_result"
>


<label
    for="ethics_result"
    class="form-label"
>

UREC / IACUC Classification

</label>


<select
    id="ethics_result"
    name="ethics_result"
    class="form-select"
    required
>

<option value="" selected disabled hidden style="display: none;">Select classification</option>

<option value="EXEMPT">
Exempt
</option>

<option value="EXPEDITED">
Expedited
</option>

<option value="FULL_BOARD">
Full-board
</option>

</select>







<label
    for="ethics_remarks"
    class="form-label mt-3"
>

Remarks

</label>


<textarea
    id="ethics_remarks"
    name="ethics_remarks"
    class="form-control"
    maxlength="5000"
    placeholder="Enter UREC/IACUC remarks, notes or recommendations when applicable."
></textarea>


<div class="admin-review-actions">


<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=ethics"
    class="btn btn-outline-asu"
>

<i class="bi bi-list-task me-1"></i>

Back to Ethics Queue

</a>


<button
    type="submit"
    class="btn btn-asu"
>

<i class="bi bi-check-circle me-1"></i>

Save UREC / IACUC Result

</button>


</div>


</form>


</section>


<?php endif; ?>


<?php
// Presentation order only: Self-Initiated shows its UPE links before
// the completed UREC/IACUC result. All review actions remain unchanged.
$placeEthicsResultAfterUpe =
    ($user['role'] ?? '') === 'faculty'
    && strtoupper(trim((string)($research['submission_track'] ?? ''))) === 'SELF_INITIATED'
    && ($research['workflow_status'] ?? '') !== 'SELF_INITIATED_COMPLETED'
    && ($research['status'] ?? '') !== 'Completed';
ob_start();
?>

<!-- =====================================================
     ETHICS RESULT COMPLETED
====================================================== -->

<?php if (
    $latestEthicsReview
    &&
    ($research['workflow_status'] ?? '') !== 'COMPLETED'
    &&
    !in_array(
        ($research['workflow_status'] ?? ''),
        [
            'SUBMITTED',
            'FOR_ETHICS_REVIEW'
        ],
        true
    )
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

UREC / IACUC Review Completed

</h4>


<p>

<strong>Classification:</strong>
<?= e(ethicsResultLabel($latestEthicsReview['result'] ?? null)); ?>

<?php if (
    !empty($latestEthicsReview['reviewed_at'])
): ?>

<br>
<strong>Recorded:</strong>
<?= e(
    date(
        'M d, Y h:i A',
        strtotime($latestEthicsReview['reviewed_at'])
    )
); ?>

<?php endif; ?>

<?php if (
    !empty($latestEthicsReview['remarks'])
): ?>

<br>
<strong>Remarks:</strong>
<?= e($latestEthicsReview['remarks']); ?>

<?php endif; ?>



<br>
<strong>Certificate:</strong>
<a
    href="<?= e(
        BASE_URL .
        '/research/view.php?id=' .
        $researchId .
        '&certificate=urec_iacuc'
    ); ?>"
    target="_blank"
    rel="noopener noreferrer"
    class="workflow-inline-link"
>
    View system-generated certificate
</a>

</p>


</div>


<?php if (
    $user['role'] === 'rps'
): ?>

<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=ethics"
    class="btn btn-outline-asu"
>

<i class="bi bi-list-task me-1"></i>

Ethics Queue

</a>

<?php endif; ?>


</div>

<?php if (
    strtoupper(trim((string)($research['submission_track'] ?? ''))) === 'SELF_INITIATED'
    && ($research['workflow_status'] ?? '') === 'SELF_INITIATED_UPE_SUBMITTED'
): ?>
<div class="rems-upload-info-note" role="note"
     style="margin-top: 14px; color: #66756b; font-size: 11px; line-height: 1.55;">
    <?php if (($user['role'] ?? '') === 'faculty'): ?>
         Submit your Completed Research / Whole Manuscript link to continue.
    <?php else: ?>
         Await the faculty researcher's Completed Research / Whole Manuscript submission.
    <?php endif; ?>
</div>
<?php endif; ?>

</section>


<?php endif; ?>


<?php
$completedEthicsCardHtml = ob_get_clean();
if (!$placeEthicsResultAfterUpe) {
    echo $completedEthicsCardHtml;
}
?>

<!-- =====================================================
     URRC REVISION WINDOW (21 days normally; per-record 2-minute testing supported)
====================================================== -->

<?php if (
    ($research['workflow_status'] ?? '') === 'URRC_REVISION_REQUIRED'
    && (int)($research['is_archived'] ?? 0) === 0
    && $isEarlyUrrcRevision
): ?>
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>URRC Revision Pending — <?= e($urrcRevisionDeadline['period_label'] ?? '21-Day Deadline'); ?></h4>
            <p>The researcher must <strong>resubmit</strong> the revised proposal within
            <?= e($urrcRevisionDeadline['period_description'] ?? '21 days'); ?> of the URRC
            <strong>Accepted with Revision</strong> decision.</p>
            <?php if (($user['role'] ?? '') === 'rps' && $hasCurrentUrrcRevisionDocument): ?>
                <p><strong>Revised manuscript saved — awaiting Faculty Submit Revision.</strong>
                   The researcher has saved the new URRC link, but must still select
                   <strong>Submit Revision</strong> in Research Details.
                   The View Revised Manuscript, verification remarks, and Proceed action
                   will appear after the final submission succeeds.</p>
            <?php endif; ?>
            <?php if ($urrcRevisionDeadline): ?>
                <p><strong>Decision date:</strong>
                    <?= e(date('M d, Y h:i A', strtotime($urrcRevisionDeadline['decision_at']))); ?><br>
                   <strong>Deadline:</strong>
                    <?= e(date('M d, Y h:i A', strtotime($urrcRevisionDeadline['deadline_at']))); ?>
                    (server time)</p>
            <?php else: ?>
                <p><strong>Deadline:</strong> Not available. Please contact the Research Office.</p>
            <?php endif; ?>
            <p>After the deadline, an unsubmitted revision is automatically archived.
            Previously uploaded files and the research history are retained.</p>
        </div>
        <?php if (($user['role'] ?? '') === 'rps'): ?>
            <a class="btn btn-outline-asu" href="<?= BASE_URL; ?>/research/rps_queue.php?queue=urrc">
                <i class="bi bi-list-task me-1"></i> Back to URRC Queue
            </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (
    ($research['workflow_status'] ?? '') === 'URRC_DID_NOT_REVISE'
    && (int)($research['is_archived'] ?? 0) === 1
): ?>
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>Archived — Did Not Revise</h4>
            <p>This research is archived because the URRC revision was not resubmitted
            within the allowed deadline, or it was archived under the existing
            Did Not Revise procedure. Its files and review records remain accessible.</p>
        </div>
        <div class="workflow-buttons">
            <a class="btn btn-outline-asu" href="<?= BASE_URL; ?>/research/archive.php">
                <i class="bi bi-archive me-1"></i> View Archives
            </a>
            <?php if (($user['role'] ?? '') === 'faculty'): ?>
            <a class="btn btn-asu" href="<?= BASE_URL; ?>/research/create.php">
                <i class="bi bi-plus-circle me-1"></i> Add New Proposal
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Legacy URRC revision handling retained for historical workflows. -->
<!-- =====================================================
     RPS - URRC DID NOT REVISE ARCHIVE ACTION
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    &&
    ($research['workflow_status'] ?? '') === 'URRC_REVISION_REQUIRED'
    && !$isEarlyUrrcRevision
    &&
    (int)($research['is_archived'] ?? 0) === 0
): ?>


<section class="workflow-card">


<div class="workflow-text mb-3">


<h4>

URRC Revision Pending

</h4>


<p>

The manuscript was marked <strong>Accepted with Revision</strong> by URRC.
If the researcher did not submit the required revised manuscript, RPS may
archive this record as <strong>Did Not Revise</strong>.

</p>


</div>


<form
    method="POST"
    class="admin-review-form"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>


<input
    type="hidden"
    name="action"
    value="archive_urrc_no_revision"
>


<label
    for="no_revision_remarks"
    class="form-label"
>

Remarks for Did Not Revise

</label>


<textarea
    id="no_revision_remarks"
    name="no_revision_remarks"
    class="form-control"
    maxlength="5000"
    placeholder="Optional: Enter note/reason before archiving."
></textarea>



<div class="admin-review-actions">


<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=urrc"
    class="btn btn-outline-asu"
>

<i class="bi bi-list-task me-1"></i>

Back to URRC Queue

</a>


<button
    type="submit"
    class="btn btn-danger"
>

<i class="bi bi-archive me-1"></i>

Mark as Did Not Revise

</button>


</div>


</form>


</section>


<?php endif; ?>


<!-- =====================================================
     RPS - REVISED URRC MANUSCRIPT: VERIFY AND PROCEED
     Existing save_urrc_result action performs the secured transition:
     URRC_REVISED_SUBMITTED -> FOR_INHOUSE on Accepted.
====================================================== -->
<?php if (
    ($user['role'] ?? '') === 'rps'
    && ($research['workflow_status'] ?? '') === 'URRC_REVISED_SUBMITTED'
    && (int)($research['is_archived'] ?? 0) === 0
): ?>
<section class="workflow-card">
    <div class="workflow-text mb-3">
        <h4>Revised URRC Manuscript Submitted</h4>
        <p>The faculty has resubmitted the revised manuscript. Verify the latest
           <strong>Revised Proposal</strong> document link before proceeding to
           <strong>In-House Review</strong>.</p>
    </div>

    <form method="POST" class="admin-review-form">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
        <input type="hidden" name="action" value="save_urrc_result">
        <input type="hidden" name="urrc_result" value="ACCEPTED">

        <?php
            $revisionLinkUrl = $phaseOneDocumentCardUrl($rpsUrrcSubmittedRevisedDocument);
        ?>
        <?php if ($revisionLinkUrl !== null): ?>
        <div class="rems-revision-inline-link-row">
            <a href="<?= e($revisionLinkUrl); ?>"
               class="rems-revision-inline-link"
               target="_blank" rel="noopener noreferrer"
               aria-label="View the latest URRC revised manuscript">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                <span>View Revised Manuscript</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($revisionLinkUrl === null): ?>
        <p class="small text-muted mt-1">The URRC submission is recorded, but the current revision link
           cannot be matched to its saved document. Check this manuscript's revision document record.
           The Proceed action is disabled to avoid approving an older link.</p>
        <?php endif; ?>

        <label for="urrc_revision_verification_remarks" class="form-label">Verification Remarks (Optional)</label>
        <textarea id="urrc_revision_verification_remarks" name="urrc_remarks"
                  class="form-control" maxlength="5000"
                  placeholder="Enter any remarks about the verified revised manuscript."></textarea>

        <div class="admin-review-actions">
            <a href="<?= BASE_URL; ?>/research/rps_queue.php?queue=urrc"
               class="btn btn-outline-asu">
                <i class="bi bi-list-task me-1"></i> Back to URRC Queue
            </a>
            <button type="submit" class="btn btn-asu"
                <?= $rpsUrrcSubmittedRevisedDocument === null ? 'disabled' : ''; ?>>
                <i class="bi bi-arrow-right-circle me-1"></i> Proceed to In-House Review
            </button>
        </div>
    </form>


</section>
<?php endif; ?>

<!-- =====================================================
     RPS - URRC REVIEW ACTIVE
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    &&
    in_array(
        ($research['workflow_status'] ?? ''),
        [
            'FOR_URRC'
        ],
        true
    )
    && !researchUrrcAwaitingRpsIntake($pdo, $researchId, $research)
): ?>


<section class="workflow-card">


<div class="workflow-text mb-3">


<h4>

URRC Review Result

</h4>


<p>

Record the URRC review for this research. Choose
<strong>Accepted with Revision</strong> if the manuscript needs revision,
or <strong>Rejected</strong> if the research should be archived.

</p>


</div>


<form
    method="POST"
    class="admin-review-form"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>


<input
    type="hidden"
    name="action"
    value="save_urrc_result"
>


<label
    for="urrc_result"
    class="form-label"
>

URRC Result

</label>


<select
    id="urrc_result"
    name="urrc_result"
    class="form-select"
    required
>

<option value="" selected disabled hidden style="display: none;">Select URRC Result</option>

<option value="ACCEPTED">
Accepted without Revision
</option>

<option value="ACCEPTED_WITH_REVISION">
Accepted with Revision
</option>

<option value="REJECTED">
Rejected
</option>

</select>


<label
    for="urrc_remarks"
    class="form-label mt-3"
>

Remarks

</label>


<textarea
    id="urrc_remarks"
    name="urrc_remarks"
    class="form-control"
    maxlength="5000"
    placeholder="Enter URRC remarks. Required for Accepted with Revision or Rejected."
></textarea>



<div class="admin-review-actions">


<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=urrc"
    class="btn btn-outline-asu"
>

<i class="bi bi-list-task me-1"></i>

Back to URRC Queue

</a>


<button
    type="submit"
    class="btn btn-asu"
>

<i class="bi bi-check-circle me-1"></i>

Save URRC Result

</button>


</div>


</form>


</section>


<?php endif; ?>


<!-- =====================================================
     URRC RESULT COMPLETED
====================================================== -->

<?php if (
    $latestUrrcReview
    &&
    ($research['workflow_status'] ?? '') !== 'COMPLETED'
    &&
    !in_array(
        ($research['workflow_status'] ?? ''),
        [
            'FOR_URRC',
            'FOR_URRC_PATH_SELECTED',
            'URRC_REVISED_SUBMITTED'
        ],
        true
    )
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

URRC Review Completed

</h4>


<p>

<strong>Result:</strong>
<?= e(str_replace('URRC ', '', (string)$latestUrrcReview['decision'])); ?>

<?php if (
    !empty($latestUrrcReview['evaluated_at'])
): ?>

<br>
<strong>Recorded:</strong>
<?= e(
    date(
        'M d, Y h:i A',
        strtotime($latestUrrcReview['evaluated_at'])
    )
); ?>

<?php endif; ?>

<?php if (
    !empty($latestUrrcReview['remarks'])
): ?>

<br>
<strong>Remarks:</strong>
<?= e($latestUrrcReview['remarks']); ?>

<?php endif; ?>

</p>


</div>


<?php if (
    $user['role'] === 'rps'
): ?>

<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=urrc"
    class="btn btn-outline-asu"
>

<i class="bi bi-list-task me-1"></i>

URRC Queue

</a>

<?php endif; ?>


</div>


</section>


<?php endif; ?>


<!-- Faculty final-submission confirmation, distinct from Edit & Save. -->
<?php if (($user['role'] ?? '') === 'faculty'
    && in_array((string)($research['workflow_status'] ?? ''),
        ['URRC_REVISED_SUBMITTED', 'INHOUSE_REVISED_SUBMITTED'], true)
    && (int)($research['is_archived'] ?? 0) === 0): ?>
<section class="workflow-card" id="revision-submitted">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>Revised Manuscript Submitted — Awaiting RPS Verification</h4>
            <p>Your <?= ($research['workflow_status'] === 'URRC_REVISED_SUBMITTED')
                ? 'URRC' : 'In-House'; ?> revision was submitted successfully.
               You do not need to submit it again. RPS will verify the
               revised manuscript and continue to the next review stage.</p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     IN-HOUSE REVISION WINDOW — SAME LAYOUT AS URRC
     180-DAY DEADLINE FOR FACULTY AND RPS
====================================================== -->
<?php if (
    ($research['workflow_status'] ?? '') === 'INHOUSE_REVISION_REQUIRED'
    && (int)($research['is_archived'] ?? 0) === 0
): ?>
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>In-House Revision Pending — 180-Day Deadline</h4>
            <p>The researcher must <strong>resubmit</strong> the revised manuscript within
               <strong>180 days</strong> of the In-House
               <strong>Passed with Revision</strong> decision.</p>
            <?php if (($user['role'] ?? '') === 'rps' && $hasCurrentInhouseRevisionDocument): ?>
                <p><strong>Revision link saved — awaiting final Faculty submission.</strong>
                   The Faculty must click <strong>Submit Revision</strong> in Research Details.
                   Verification Remarks and Proceed to UREC/IACUC Review appear only
                   after that submission succeeds.</p>
            <?php endif; ?>
            <?php if (($user['role'] ?? '') === 'rps' && !$hasCurrentInhouseRevisionDocument): ?>
                <p>Awaiting the Faculty's saved revised manuscript and final
                   <strong>Submit Revision</strong>. Once successfully submitted, the
                   verification section will appear here.</p>
            <?php endif; ?>
            <?php if ($inhouseRevisionDeadline): ?>
                <p><strong>Decision date:</strong>
                    <?= e(date('M d, Y h:i A', strtotime($inhouseRevisionDeadline['decision_at']))); ?><br>
                   <strong>Deadline:</strong>
                    <?= e(date('M d, Y h:i A', strtotime($inhouseRevisionDeadline['deadline_at']))); ?>
                    (server time)</p>
            <?php else: ?>
                <p><strong>Deadline:</strong> Not available. Please contact the Research Office.</p>
            <?php endif; ?>
            <p>After the deadline, an unsubmitted revision is automatically archived.
               Original documents and review history are retained.</p>
        </div>
        <?php if (($user['role'] ?? '') === 'rps'): ?>
            <a class="btn btn-outline-asu"
               href="<?= BASE_URL; ?>/research/rps_queue.php?queue=inhouse">
                <i class="bi bi-list-task me-1"></i> Back to In-House Queue
            </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     FACULTY ONLY — IN-HOUSE 180-DAY DEADLINE EXPIRED
     Keep archived research readable; offer Self-Initiated afterward.
====================================================== -->
<?php if (
    ($user['role'] ?? '') === 'faculty'
    && ($research['workflow_status'] ?? '') === 'INHOUSE_DID_NOT_REVISE'
    && (int)($research['is_archived'] ?? 0) === 1
    && ($research['submission_track'] ?? '') === 'REGULAR'
    && researchSelectedEarlyPath($pdo, $researchId, 'REGULAR')
    && $inhouseRevisionDeadline
    && (int)$inhouseRevisionDeadline['expired'] === 1
): ?>
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>In-House Revision Deadline Expired</h4>
            <p>The 180-day period has ended. Your original research files and review history
                are preserved. You may now proceed through the Self-Initiated pathway.</p>
        </div>
        <div class="workflow-buttons">
            <form method="POST" class="d-inline-block m-0"
                  onsubmit="return confirm('Proceed to Self-Initiated? Your archived research will continue with UPE Certificates and UREC/IACUC review. The previous archive event remains in audit history.');">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                <input type="hidden" name="action" value="proceed_to_self_initiated_from_inhouse">
                <button type="submit" class="btn btn-outline-asu">
                    <i class="bi bi-arrow-right-circle me-1"></i> Proceed to Self-Initiated
                </button>
            </form>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     RPS — REVISED IN-HOUSE MANUSCRIPT: VERIFY AND PROCEED
     Matches URRC revised-review buttons and layout.
     Existing save_inhouse_result action advances to UREC/IACUC.
====================================================== -->
<?php if (
    ($user['role'] ?? '') === 'rps'
    && ($research['workflow_status'] ?? '') === 'INHOUSE_REVISED_SUBMITTED'
    && (int)($research['is_archived'] ?? 0) === 0
): ?>
<section class="workflow-card">
    <div class="workflow-text mb-3">
        <h4>Revised In-House Manuscript Submitted</h4>
        <p>The faculty has resubmitted the revised manuscript. Verify the latest
           <strong>Revised Proposal</strong> document link from the manuscript summary
           before proceeding to <strong>UREC/IACUC Review</strong>.</p>
    </div>

    <form method="POST" class="admin-review-form">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
        <input type="hidden" name="action" value="save_inhouse_result">
        <input type="hidden" name="inhouse_result" value="PASSED">

        <?php
            $revisionLinkUrl = $phaseOneDocumentCardUrl($rpsInhouseSubmittedRevisedDocument);
        ?>
        <?php if ($revisionLinkUrl !== null): ?>
        <div class="rems-revision-inline-link-row">
            <a href="<?= e($revisionLinkUrl); ?>"
               class="rems-revision-inline-link"
               target="_blank" rel="noopener noreferrer"
               aria-label="View the latest In-House revised manuscript">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                <span>View Revised Manuscript</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if ($revisionLinkUrl === null): ?>
        <p class="small text-muted mt-1">The In-House revision is marked submitted,
           but its current-round document link could not be verified. Ask the
           Faculty to confirm the saved link; do not use an older URRC link.</p>
        <?php endif; ?>

        <label for="inhouse_revision_verification_remarks" class="form-label">Verification Remarks (Optional)</label>
        <textarea id="inhouse_revision_verification_remarks" name="inhouse_remarks"
                  class="form-control" maxlength="5000"
                  placeholder="Enter any remarks about the verified revised manuscript."></textarea>

        <div class="admin-review-actions">
            <a href="<?= BASE_URL; ?>/research/rps_queue.php?queue=inhouse"
               class="btn btn-outline-asu">
                <i class="bi bi-list-task me-1"></i> Back to In-House Queue
            </a>
            <button type="submit" class="btn btn-asu"
                <?= ($rpsInhouseSubmittedRevisedDocument === null) ? 'disabled' : ''; ?>>
                <i class="bi bi-arrow-right-circle me-1"></i> Proceed to UREC/IACUC Review
            </button>
        </div>
    </form>
</section>
<?php endif; ?>

<!-- =====================================================
     RPS - IN-HOUSE REVIEW ACTIVE
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    &&
    in_array(
        ($research['workflow_status'] ?? ''),
        [
            'FOR_INHOUSE'
        ],
        true
    )
): ?>


<section class="workflow-card">


<div class="workflow-text mb-3">


<h4>

In-House Review Result

</h4>


<p>

Record the In-House decision for this research. Choose
<strong>Passed</strong> to move to UREC/IACUC Review,
<strong>Passed with Revision</strong> if the researcher must revise,
or <strong>Failed</strong> if the research should be archived.

</p>


</div>


<form
    method="POST"
    class="admin-review-form"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>


<input
    type="hidden"
    name="action"
    value="save_inhouse_result"
>


<label
    for="inhouse_result"
    class="form-label"
>

In-House Result

</label>


<select
    id="inhouse_result"
    name="inhouse_result"
    class="form-select"
    required
>

<option value="" selected disabled hidden style="display: none;">Select In-House Result</option>

<option value="PASSED">
Passed without Revision
</option>

<option value="PASSED_WITH_REVISION">
Passed with Revision
</option>

<option value="FAILED">
Failed
</option>

</select>


<label
    for="inhouse_remarks"
    class="form-label mt-3"
>

Remarks

</label>


<textarea
    id="inhouse_remarks"
    name="inhouse_remarks"
    class="form-control"
    maxlength="5000"
    placeholder="Enter In-House remarks. Required if the result is Passed with Revision or Failed."
></textarea>


<div class="admin-review-actions">


<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=inhouse"
    class="btn btn-outline-asu"
>

<i class="bi bi-list-task me-1"></i>

Back to In-House Queue

</a>


<button
    type="submit"
    class="btn btn-asu"
>

<i class="bi bi-check-circle me-1"></i>

Save In-House Result

</button>


</div>


</form>


</section>


<?php endif; ?>


<!-- =====================================================
     IN-HOUSE RESULT COMPLETED
====================================================== -->

<?php if (
    $latestInhouseReview
    &&
    ($research['workflow_status'] ?? '') !== 'COMPLETED'
    &&
    !in_array(
        ($research['workflow_status'] ?? ''),
        [
            'FOR_INHOUSE',
            'INHOUSE_REVISED_SUBMITTED'
        ],
        true
    )
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

<?= ($research['workflow_status'] ?? '') === 'INHOUSE_REVISION_REQUIRED'
    ? 'In-House Revision Requested' : 'In-House Review Completed'; ?>

</h4>


<p>

<strong>Result:</strong>
<?= e(str_replace('In-House ', '', (string)$latestInhouseReview['decision'])); ?>

<?php if (
    !empty($latestInhouseReview['evaluated_at'])
): ?>

<br>
<strong>Recorded:</strong>
<?= e(
    date(
        'M d, Y h:i A',
        strtotime($latestInhouseReview['evaluated_at'])
    )
); ?>

<?php endif; ?>

<?php if (
    !empty($latestInhouseReview['remarks'])
): ?>

<br>
<strong>Remarks:</strong>
<?= e($latestInhouseReview['remarks']); ?>

<?php endif; ?>

</p>


</div>


<?php if (
    $user['role'] === 'rps'
): ?>

<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=inhouse"
    class="btn btn-outline-asu"
>

<i class="bi bi-list-task me-1"></i>

In-House Queue

</a>

<?php endif; ?>


</div>


</section>


<?php endif; ?>


<!-- =====================================================
     RPS - READY FOR NOTICE TO PROCEED
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    &&
    ($research['workflow_status'] ?? '') === 'READY_FOR_NTP'
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Ready to Generate Notice to Proceed

</h4>


<p>

Generate the Notice to Proceed and move this research to implementation.

</p>


</div>


<form
    method="POST"
    class="admin-review-form"
    id="generateNtpForm"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>

<input
    type="hidden"
    name="action"
    value="issue_notice_to_proceed"
>

<input
    type="hidden"
    name="ntp_remarks"
    value="Notice to Proceed generated by RPS."
>

<div class="admin-review-actions ntp-action-row ntp-ready-actions">

<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=ntp"
    class="btn btn-outline-asu"
>

<i class="bi bi-list-task me-1"></i>

Back to NTP Queue

</a>

<button
    type="submit"
    class="btn btn-asu"
>

<i class="bi bi-file-earmark-check me-1"></i>

Generate Notice to Proceed

</button>

</div>

</form>


</div>


</section>


<?php endif; ?>



<!-- =====================================================
     NOTICE TO PROCEED ISSUED
====================================================== -->

<?php if (
    ($research['workflow_status'] ?? '') === 'NTP_ISSUED'
): ?>


<section class="workflow-card ntp-issued-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Notice to Proceed Generated

</h4>


<p>

This research may now proceed to implementation. The Notice to Proceed can be
viewed, printed, or saved as PDF.

</p>

<p class="ntp-next-step-note">
<i class="bi bi-info-circle" aria-hidden="true"></i>
<strong>Next Step:</strong> Click the <strong>Project Monitoring</strong> button to proceed to the monitoring stage.
</p>

</div>


<div class="admin-review-actions ntp-action-row ntp-generated-actions">

<a
    href="<?= e($generatedNoticeToProceedUrl); ?>"
    class="btn btn-asu"
>

<i class="bi bi-file-earmark-check me-1"></i>

View Notice to Proceed

</a>

<?php if (
    in_array(
        $user['role'],
        [
            'faculty',
            'admin',
            'rps'
        ],
        true
    )
): ?>

<a
    href="<?= BASE_URL; ?>/research/progress.php?id=<?= (int)$researchId; ?>"
    class="btn btn-outline-asu"
>

<i class="bi bi-graph-up-arrow me-1"></i>

Project Monitoring

</a>

<?php endif; ?>


</div>


</div>


</section>


<?php endif; ?>





<!-- =====================================================
     RPS - COMPLETED MANUSCRIPT IN-HOUSE CHECK
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    &&
    ($research['workflow_status'] ?? '') === 'COMPLETED_MANUSCRIPT_SUBMITTED'
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Completed Manuscript for In-House Check

</h4>


<p>

The researcher has submitted the manuscript of completed research. Click
<strong>OK</strong> once checked to proceed to the next requirement.

</p>


</div>


<form
    method="POST"
    class="admin-review-form"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>

<input
    type="hidden"
    name="action"
    value="confirm_completed_manuscript_inhouse"
>

<div class="admin-review-actions ntp-action-row ntp-generated-actions">

<a
    href="<?= BASE_URL; ?>/research/progress.php?id=<?= (int)$researchId; ?>"
    class="btn btn-outline-asu"
>
<i class="bi bi-eye me-1"></i>
View Submitted Manuscript
</a>

<button
    type="submit"
    class="btn btn-asu"
>
<i class="bi bi-check-circle me-1"></i>
OK
</button>

</div>

</form>


</div>


</section>


<?php endif; ?>



<!-- =====================================================
     COMPLETED MANUSCRIPT CONFIRMED
====================================================== -->

<?php if (
    ($research['workflow_status'] ?? '') === 'FOR_TERMINAL_REPORT'
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Completed Manuscript Confirmed

</h4>


<p>

The completed manuscript has been checked by RPS. The research is now ready
for the next requirement.

</p>


</div>


<a
    href="<?= BASE_URL; ?>/research/progress.php?id=<?= (int)$researchId; ?>"
    class="btn btn-outline-asu"
>
<i class="bi bi-graph-up-arrow me-1"></i>
Project Monitoring
</a>


</div>


</section>


<?php endif; ?>





<!-- =====================================================
     RPS — TERMINAL REPORT OK BEFORE PERMITS
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    && ($research['workflow_status'] ?? '') === 'TERMINAL_REPORT_SUBMITTED'
    && !$terminalReportRpsConfirmed
): ?>

<section class="workflow-card">

<div class="workflow-content">

<div class="workflow-text">
<h4>Terminal Report for RPS Confirmation</h4>
<p>
The researcher has submitted the Terminal Report. Click
<strong>OK</strong> once checked to make the Publication and Presentation
permit generation available.
</p>
</div>

<form method="POST" class="admin-review-form">
<input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
<input type="hidden" name="action" value="confirm_terminal_report_rps">

<div class="admin-review-actions ntp-action-row ntp-generated-actions">
<button type="submit" class="btn btn-asu">
<i class="bi bi-check-circle me-1"></i>
OK
</button>
</div>
</form>

</div>

</section>

<?php endif; ?>

<!-- =====================================================
     RPS — PERMIT PROCESSING (ONLY AFTER TERMINAL REPORT OK)
====================================================== -->

<?php if (
    $user['role'] === 'rps'
    && ($research['workflow_status'] ?? '') === 'TERMINAL_REPORT_SUBMITTED'
    && $terminalReportRpsConfirmed
): ?>

<section class="workflow-card">

<div class="workflow-content" style="align-items:flex-start;">
<div class="workflow-text">
<h4>Permit for Publication / Presentation</h4>
<p>
The Terminal Report has been confirmed by RPS. Generate both the
Publication and Presentation permits with one action.
</p>
</div>
</div>

<form method="POST" class="admin-review-form mt-3" id="issuePermitForm">

<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>

<input
    type="hidden"
    name="action"
    value="issue_publication_presentation_permit"
>

<div class="row g-3 align-items-end">

<div class="col-lg-5">
<label class="form-label fw-semibold">Permits to Generate</label>
<div class="form-control d-flex align-items-center" style="min-height:43px; background:#f7fbf8;">
    <span><i class="bi bi-check-circle-fill text-success me-1"></i> Publication
    <span class="mx-2 text-muted">+</span>
    <i class="bi bi-check-circle-fill text-success me-1"></i> Presentation</span>
</div>
</div>

<div class="col-lg-5">
<label for="permitRemarks" class="form-label fw-semibold">
RPS Remarks <span class="text-muted fw-normal">(optional)</span>
</label>
<input
    id="permitRemarks"
    name="permit_remarks"
    type="text"
    class="form-control"
    maxlength="5000"
    placeholder="Optional note or condition"
>
</div>

<div class="col-lg-2 d-grid">
<button type="submit" class="btn btn-asu">
<i class="bi bi-patch-check me-1"></i>
Generate Both Permits
</button>
</div>

</div>

<div class="research-field-note mt-2">
Both permits will be generated at the same time. View or print each permit separately after issuance.
</div>

</form>

</section>

<?php endif; ?>


<!-- =====================================================
     PERMIT ISSUED
====================================================== -->

<?php if (
    ($research['workflow_status'] ?? '') === 'PERMIT_ISSUED'
): ?>

<section class="workflow-card">

<div class="workflow-content">

<div class="workflow-text">

<h4>
<?= $bothPermitsIssued ? 'Publication and Presentation Permits Issued' : 'Publication / Presentation Permit Issued'; ?>
</h4>

<p>
<?= $bothPermitsIssued
    ? 'The Research Office has generated both permits. Each system-generated permit can be viewed, printed, or saved as PDF separately.'
    : 'The Research Office has issued the research permit. The system-generated permit is available for viewing, printing, and record purposes.'; ?>
</p>

</div>

<div class="workflow-buttons ntp-action-row">

<?php if ($bothPermitsIssued): ?>
<a href="<?= e($publicationPermitUrl); ?>" class="btn btn-outline-asu">
<i class="bi bi-file-earmark-text me-1"></i>
View Publication Permit
</a>
<a href="<?= e($presentationPermitUrl); ?>" class="btn btn-outline-asu">
<i class="bi bi-file-earmark-slides me-1"></i>
View Presentation Permit
</a>
<?php else: ?>
<a href="<?= e($generatedPermitUrl); ?>" class="btn btn-asu">
<i class="bi bi-file-earmark-check me-1"></i>
View / Print Permit
</a>
<?php endif; ?>

<?php if (
    $user['role'] === 'rps'
): ?>

<a
    href="<?= BASE_URL; ?>/research/rps_queue.php?queue=permit"
    class="btn btn-outline-asu"
>
<i class="bi bi-list-task me-1"></i>
Back to Permit Queue
</a>

<?php endif; ?>

</div>

</div>

</section>

<?php endif; ?>


<!-- Final record retains the issued permits above Proof and Congratulations. -->
<?php if (
    $bothPermitsIssued
    && strtoupper(trim((string)($research['submission_track'] ?? ''))) !== 'SELF_INITIATED'
    && ($research['workflow_status'] ?? '') === 'COMPLETED'
): ?>
<section class="workflow-card">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>Issued Research Permits</h4>
            <p>Publication and Presentation permits remain available for this research record.</p>
        </div>
        <div class="workflow-buttons ntp-action-row">
            <a href="<?= e($publicationPermitUrl); ?>" class="btn btn-outline-asu">View Publication Permit</a>
            <a href="<?= e($presentationPermitUrl); ?>" class="btn btn-outline-asu">View Presentation Permit</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     REGULAR RESEARCH — PROOF REQUIRED AFTER BOTH PERMITS
     Faculty submits the link in Project Monitoring; RPS can view status.
====================================================== -->

<?php if (
    strtoupper(trim((string)($research['submission_track'] ?? ''))) !== 'SELF_INITIATED'
    && $bothPermitsIssued
    && in_array((string)($research['workflow_status'] ?? ''), ['PERMIT_ISSUED', 'COMPLETED'], true)
): ?>
<section class="workflow-card" aria-label="Proof of publication or presentation">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>Proof of Publication / Presentation</h4>
            <?php if ($regularFinalProofSubmitted): ?>
                <p>Proof submitted. The final research requirement has been recorded.</p>
            <?php else: ?>
                <p>The permits are issued. The faculty researcher must submit the proof link before the research is marked Completed.</p>
            <?php endif; ?>
        </div>
        <div class="workflow-buttons ntp-action-row">
            <?php if ($regularFinalProofSubmitted): ?>
                <a href="<?= BASE_URL; ?>/research/view.php?id=<?= (int)$researchId; ?>&amp;document_id=<?= (int)$regularFinalProofDocument['document_id']; ?>&amp;file_mode=view" class="btn btn-outline-asu">
                    <i class="bi bi-eye me-1"></i> View Submitted Proof
                </a>
            <?php elseif (($user['role'] ?? '') === 'faculty'
                && (int)($research['researcher_id'] ?? 0) === (int)($user['user_id'] ?? 0)): ?>
                <a href="<?= BASE_URL; ?>/research/progress.php?id=<?= (int)$researchId; ?>" class="btn btn-asu">
                    <i class="bi bi-upload me-1"></i> Submit Proof
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<!-- =====================================================
     FINAL RESEARCH COMPLETION
====================================================== -->

<?php if (
    (
        ($research['workflow_status'] ?? '') === 'COMPLETED'
        || ($research['status'] ?? '') === 'Completed'
    )
    && (
        strtoupper(trim((string)($research['submission_track'] ?? ''))) === 'SELF_INITIATED'
        || $regularFinalProofSubmitted
    )
): ?>

<section class="workflow-card research-final-completion">

<div class="workflow-content align-items-center">

<div class="d-flex align-items-start gap-3">

<div
    class="d-flex align-items-center justify-content-center flex-shrink-0"
    style="width:46px;height:46px;border-radius:14px;background:#eaf7ee;color:#08752c;font-size:22px;"
>
<i class="bi bi-check2-circle"></i>
</div>

<div class="workflow-text">

<div
    class="text-uppercase fw-bold mb-1"
    style="font-size:10px;letter-spacing:.08em;color:#08752c;"
>
Research Record Complete
</div>

<h4 class="mb-2">
<?php if (
    ($user['role'] ?? '') === 'faculty'
    && (int)($research['researcher_id'] ?? 0) === (int)($user['user_id'] ?? 0)
    && ($research['status'] ?? '') === 'Completed'
    && in_array((string)($research['workflow_status'] ?? ''), ['COMPLETED', 'SELF_INITIATED_COMPLETED'], true)
): ?>
Congratulations! Your Research Is Complete!
<?php else: ?>
Research Workflow Completed
<?php endif; ?>
</h4>

<p class="mb-2">
<?php if (
    ($user['role'] ?? '') === 'faculty'
    && (int)($research['researcher_id'] ?? 0) === (int)($user['user_id'] ?? 0)
    && ($research['status'] ?? '') === 'Completed'
    && in_array((string)($research['workflow_status'] ?? ''), ['COMPLETED', 'SELF_INITIATED_COMPLETED'], true)
): ?>
<strong>Well done!</strong> You have successfully completed all required steps of your research.
Your completed record is now available in ASU-REMS for future reference.
<?php endif; ?>
<?php if (
    strtoupper((string)($research['submission_track'] ?? ''))
    === 'SELF_INITIATED'
): ?>
Open the final record to view the complete research workflow.
<?php else: ?>
Open the final record to view the complete research workflow.
<?php endif; ?>
</p>

<div class="d-flex flex-wrap gap-3 mt-2" style="font-size:11px;color:#66736a;">

<span>
<i class="bi bi-check-circle-fill me-1 text-success"></i>
Status: <strong class="text-dark">Completed</strong>
</span>

<span>
<i class="bi bi-bar-chart-fill me-1 text-success"></i>
Progress: <strong class="text-dark">100%</strong>
</span>

<?php if (!empty($research['completed_at'])): ?>
<span>
<i class="bi bi-calendar-check me-1 text-success"></i>
Completed: <strong class="text-dark"><?= e(date('M d, Y', strtotime($research['completed_at']))); ?></strong>
</span>
<?php endif; ?>

</div>

</div>

</div>

<div class="workflow-buttons">
<a
    href="<?= BASE_URL; ?>/research/progress.php?id=<?= (int)$researchId; ?>"
    class="btn btn-outline-asu"
>
<i class="bi bi-folder2-open me-1"></i>
View Final Record
</a>
</div>

</div>

</section>
<?php endif; ?>


<?php
/*
|--------------------------------------------------------------------------
| COMPLETED SELF-INITIATED DISPLAY
|--------------------------------------------------------------------------
| Once the Self-Initiated workflow is complete, the individual upload steps
| are no longer repeated on this details page. The complete final workflow
| is available through "View Final Record" in progress.php.
|--------------------------------------------------------------------------
*/
$isCompletedSelfInitiatedDisplay =
    strtoupper(
        trim(
            (string)(
                $research['submission_track']
                ?? ''
            )
        )
    ) === 'SELF_INITIATED'
    &&
    (
        ($research['workflow_status'] ?? '')
        === 'SELF_INITIATED_COMPLETED'
        ||
        ($research['status'] ?? '')
        === 'Completed'
    );
?>

<?php if (!$isCompletedSelfInitiatedDisplay): ?>


<!-- =====================================================
     FACULTY - CHOOSE PATHWAY AFTER UREC / IACUC
====================================================== -->

<?php if (
    $user['role'] === 'faculty'
    &&
    (int)$research['researcher_id'] === (int)$user['user_id']
    &&
    ($research['workflow_status'] ?? '') === 'ETHICS_COMPLETED'
): ?>

<section class="workflow-card">

<div class="workflow-content">

<div class="workflow-text">

<h4>
UREC / IACUC Review Completed
</h4>

<p>
Your UREC/IACUC processing is complete. Choose the appropriate next research pathway below.
RPS comments do not determine or change this pathway.
</p>

</div>

<div class="ethics-choice-actions">

<!-- NORMAL / FOR URRC PATH -->
<form method="POST">
<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>
<input
    type="hidden"
    name="action"
    value="continue_after_ethics"
>
<button
    type="submit"
    class="btn btn-asu"
>
<i class="bi bi-arrow-right-circle me-1"></i>
For URRC
</button>
</form>

<!-- SELF-INITIATED / UPE PATH -->
<form method="POST">
<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>
<input
    type="hidden"
    name="action"
    value="select_self_initiated"
>
<button
    type="submit"
    class="btn btn-self-initiated"
>
<i class="bi bi-person-check me-1"></i>
Self-Initiated
</button>
</form>

</div>

</div>

</section>

<?php endif; ?>


<!-- =====================================================
     SELF-INITIATED - STEP 1 UPE CERTIFICATES
====================================================== -->

<?php if (
    $user['role'] === 'faculty'
    &&
    (int)$research['researcher_id'] === (int)$user['user_id']
    &&
    in_array(
        (string)($research['workflow_status'] ?? ''),
        [
            'SELF_INITIATED_UPE_REQUIRED',
            'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS',
            'SELF_INITIATED_UPE_SUBMITTED',
            'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED',
            'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED',
            'SELF_INITIATED_PERMIT_GENERATED',
            'SELF_INITIATED_COMPLETED'
        ],
        true
    )
): ?>

<section class="workflow-card upe-step-card">

<div class="workflow-text">

<h4>
Self-Initiated Research — UPE Certificates
</h4>

<p>
Provide two certificate links from the University Pool of Experts (UPE).
Both certificates must be submitted before the next Self-Initiated step can proceed.
Paste a shareable HTTPS link for each certificate.
</p>

</div>

<div class="upe-upload-grid">

<!-- UPE CERTIFICATE 1 -->
<div class="upe-upload-item<?= $hasUpeCertificate1Document ? ' is-complete' : ''; ?>">

<div class="upe-upload-head">
<div>
<h5>UPE Certificate 1</h5>
<p>First required UPE certificate.</p>
</div>

<?php if ($hasUpeCertificate1Document): ?>
<span class="upe-upload-status">
<i class="bi bi-check-circle-fill"></i>
Uploaded
</span>
<?php endif; ?>
</div>

<?php if ($hasUpeCertificate1Document && $upeCertificate1Document): ?>

<div class="upe-complete-file">
<i class="bi bi-file-earmark-check-fill"></i>
<div>
<strong><?= e($upeCertificate1Document['original_name']); ?></strong>
<small>
<?= in_array(strtolower((string)$upeCertificate1Document['file_extension']), ['url', 'link'], true) ? 'External link' : e(researchFileSize((int)$upeCertificate1Document['file_size'])); ?>
</small>
</div>
<a
    class="btn btn-sm btn-outline-success upe-certificate-eye-btn"
    aria-label="View UPE Certificate 1"
    title="View UPE Certificate 1"
    href="<?= e(
        BASE_URL
        . '/research/view.php?id='
        . $researchId
        . '&document_id='
        . (int)$upeCertificate1Document['document_id']
        . '&file_mode=view'
    ); ?>"
>
<i class="bi bi-eye" aria-hidden="true"></i>
</a>
</div>

<?php elseif (
    ($research['workflow_status'] ?? '') === 'SELF_INITIATED_UPE_REQUIRED'
): ?>

<form
    method="POST"
    enctype="multipart/form-data"
    class="upe-upload-form"
>
<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>
<input
    type="hidden"
    name="action"
    value="upload_upe_certificate"
>
<input
    type="hidden"
    name="upe_slot"
    value="1"
>
<div class="rems-document-link-row">
    <input type="url" name="upe_certificate_link" class="form-control rems-document-link-input"
           placeholder="https://drive.google.com/file/d/..." maxlength="255" data-research-document-link
           aria-label="Paste secure document link" required>
    <a href="#" class="btn btn-outline-asu rems-link-preview disabled"
       data-rems-view-link target="_blank" rel="noopener noreferrer" aria-disabled="true"
       aria-label="View document link" title="View link in a new tab">
        <i class="bi bi-eye" aria-hidden="true"></i>
    </a>
</div>
<div class="form-note rems-upload-view-note rems-upload-info-note" data-rems-view-note role="status" aria-live="polite">
Paste a shareable HTTPS link. You must click the eye button to view it before submitting.
</div>
<button
    type="submit"
    class="btn btn-asu btn-sm"
    disabled
    title="View the document link first"
>
<i class="bi bi-cloud-arrow-up me-1"></i>
Upload Certificate 1
</button>
</form>

<?php endif; ?>

</div>


<!-- UPE CERTIFICATE 2 -->
<div class="upe-upload-item<?= $hasUpeCertificate2Document ? ' is-complete' : ''; ?>">

<div class="upe-upload-head">
<div>
<h5>UPE Certificate 2</h5>
<p>Second required UPE certificate.</p>
</div>

<?php if ($hasUpeCertificate2Document): ?>
<span class="upe-upload-status">
<i class="bi bi-check-circle-fill"></i>
Uploaded
</span>
<?php endif; ?>
</div>

<?php if ($hasUpeCertificate2Document && $upeCertificate2Document): ?>

<div class="upe-complete-file">
<i class="bi bi-file-earmark-check-fill"></i>
<div>
<strong><?= e($upeCertificate2Document['original_name']); ?></strong>
<small>
<?= in_array(strtolower((string)$upeCertificate2Document['file_extension']), ['url', 'link'], true) ? 'External link' : e(researchFileSize((int)$upeCertificate2Document['file_size'])); ?>
</small>
</div>
<a
    class="btn btn-sm btn-outline-success upe-certificate-eye-btn"
    aria-label="View UPE Certificate 2"
    title="View UPE Certificate 2"
    href="<?= e(
        BASE_URL
        . '/research/view.php?id='
        . $researchId
        . '&document_id='
        . (int)$upeCertificate2Document['document_id']
        . '&file_mode=view'
    ); ?>"
>
<i class="bi bi-eye" aria-hidden="true"></i>
</a>
</div>

<?php elseif (
    ($research['workflow_status'] ?? '') === 'SELF_INITIATED_UPE_REQUIRED'
): ?>

<form
    method="POST"
    enctype="multipart/form-data"
    class="upe-upload-form"
>
<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>
<input
    type="hidden"
    name="action"
    value="upload_upe_certificate"
>
<input
    type="hidden"
    name="upe_slot"
    value="2"
>
<div class="rems-document-link-row">
    <input type="url" name="upe_certificate_link" class="form-control rems-document-link-input"
           placeholder="https://drive.google.com/file/d/..." maxlength="255" data-research-document-link
           aria-label="Paste secure document link" required>
    <a href="#" class="btn btn-outline-asu rems-link-preview disabled"
       data-rems-view-link target="_blank" rel="noopener noreferrer" aria-disabled="true"
       aria-label="View document link" title="View link in a new tab">
        <i class="bi bi-eye" aria-hidden="true"></i>
    </a>
</div>
<div class="form-note rems-upload-view-note rems-upload-info-note" data-rems-view-note role="status" aria-live="polite">
Paste a shareable HTTPS link. You must click the eye button to view it before submitting.
</div>
<button
    type="submit"
    class="btn btn-asu btn-sm"
    disabled
    title="View the document link first"
>
<i class="bi bi-cloud-arrow-up me-1"></i>
Upload Certificate 2
</button>
</form>

<?php endif; ?>

</div>

</div>

<?php if (
    $hasUpeCertificate1Document
    && $hasUpeCertificate2Document
    && ($research['workflow_status'] ?? '') === 'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS'
): ?>
<div class="rems-upload-info-note" role="status"
     style="margin-top: 14px; color: #66756b; font-size: 11px; line-height: 1.55;">
    Please wait for RPS to initiate the UREC/IACUC review.
    The next step will be available once the UREC/IACUC result is recorded.
</div>
<?php endif; ?>

</section>

<?php endif; ?>

<?php
// For Self-Initiated faculty, the unchanged UREC completion card follows UPE.
if ($placeEthicsResultAfterUpe) {
    echo $completedEthicsCardHtml;
}
?>

<?php if (
    ($user['role'] ?? '') === 'rps'
    && $hasUpeCertificate1Document
    && $hasUpeCertificate2Document
    && ($research['workflow_status'] ?? '') === 'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS'
): ?>
<section class="workflow-card" aria-label="UREC / IACUC processing action">
    <div class="workflow-content">
        <div class="workflow-text">
            <h4>UREC / IACUC Review</h4>
            <p>Both UPE certificates are ready for ethics processing.</p>
        </div>
        <form method="POST" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="start_ethics_review">
            <button type="submit" class="btn btn-asu">
                <i class="bi bi-shield-check me-1"></i> Start UREC / IACUC Processing
            </button>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     SELF-INITIATED - COMPLETED RESEARCH / WHOLE MANUSCRIPT
====================================================== -->

<?php if (
    $user['role'] === 'faculty'
    &&
    (int)$research['researcher_id'] === (int)$user['user_id']
    &&
    in_array(
        (string)($research['workflow_status'] ?? ''),
        [
            'SELF_INITIATED_UPE_SUBMITTED',
            'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED',
            'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED',
            'SELF_INITIATED_PERMIT_GENERATED',
            'SELF_INITIATED_COMPLETED'
        ],
        true
    )
): ?>

<section class="workflow-card upe-step-card">

<div class="workflow-text">

<h4>
Self-Initiated Research — Completed Research / Whole Manuscript
</h4>

<p>
Submit a shareable HTTPS link for the completed research or whole manuscript.
</p>

</div>

<div class="upe-upload-grid" style="grid-template-columns: minmax(0, 1fr);">

<div class="upe-upload-item<?= $hasSelfInitiatedCompletedResearchDocument ? ' is-complete' : ''; ?>">

<?php if (
    $hasSelfInitiatedCompletedResearchDocument
    &&
    $selfInitiatedCompletedResearchDocument
): ?>

<div class="upe-upload-head">
<div>
<h5>Research Document</h5>
<p>Final research manuscript.</p>
</div>
<span class="upe-upload-status">
<i class="bi bi-check-circle-fill"></i>
Uploaded
</span>
</div>

<div class="upe-complete-file">
<i class="bi bi-file-earmark-check-fill"></i>
<div>
<strong><?= e($selfInitiatedCompletedResearchDocument['original_name']); ?></strong>
<small>
<?= in_array(strtolower((string)$selfInitiatedCompletedResearchDocument['file_extension']), ['url', 'link'], true) ? 'External link' : e(researchFileSize((int)$selfInitiatedCompletedResearchDocument['file_size'])); ?>
</small>
</div>
<a
    class="btn btn-sm btn-outline-success"
    href="<?= e(
        BASE_URL
        . '/research/view.php?id='
        . $researchId
        . '&document_id='
        . (int)$selfInitiatedCompletedResearchDocument['document_id']
        . '&file_mode=view'
    ); ?>"
>
View
</a>
</div>

<?php elseif (
    ($research['workflow_status'] ?? '')
    === 'SELF_INITIATED_UPE_SUBMITTED'
): ?>

<form
    method="POST"
    enctype="multipart/form-data"
    class="upe-upload-form"
>
<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>
<input
    type="hidden"
    name="action"
    value="upload_self_initiated_completed_research"
>
<div class="rems-document-link-row">
    <input type="url" name="self_initiated_completed_research_link" class="form-control rems-document-link-input"
           placeholder="https://drive.google.com/file/d/..." maxlength="255" data-research-document-link
           aria-label="Paste secure document link" required>
    <a href="#" class="btn btn-outline-asu rems-link-preview disabled"
       data-rems-view-link target="_blank" rel="noopener noreferrer" aria-disabled="true"
       aria-label="View document link" title="View link in a new tab">
        <i class="bi bi-eye" aria-hidden="true"></i>
    </a>
</div>
<div class="form-note rems-upload-view-note rems-upload-info-note" data-rems-view-note role="status" aria-live="polite">
Paste a shareable HTTPS link. You must click the eye button to view it before submitting.
</div>
<button
    type="submit"
    class="btn btn-asu btn-sm"
    disabled
    title="View the document link first"
>
<i class="bi bi-cloud-arrow-up me-1"></i>
Upload Completed Research
</button>
</form>

<?php endif; ?>

</div>

</div>

</section>

<?php endif; ?>


<!-- =====================================================
     SELF-INITIATED - TERMINAL REPORT
====================================================== -->

<?php if (
    $user['role'] === 'faculty'
    &&
    (int)$research['researcher_id'] === (int)$user['user_id']
    &&
    in_array(
        (string)($research['workflow_status'] ?? ''),
        [
            'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED',
            'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED',
            'SELF_INITIATED_PERMIT_GENERATED',
            'SELF_INITIATED_COMPLETED'
        ],
        true
    )
): ?>

<section class="workflow-card upe-step-card">

<div class="workflow-text">

<h4>
Self-Initiated Research — Terminal Report
</h4>

<p>
Submit the Terminal Report using a shareable HTTPS link.
The Research Title and Researcher are filled automatically.
</p>

</div>

<div class="row g-3 mb-3">

<div class="col-lg-6">
<label class="form-label fw-semibold">Research Title</label>
<input
    type="text"
    class="form-control"
    value="<?= e($research['research_title']); ?>"
    readonly
>
</div>

<div class="col-lg-6">
<label class="form-label fw-semibold">Researcher</label>
<input
    type="text"
    class="form-control"
    value="<?= e($researcherName); ?>"
    readonly
>
</div>

</div>

<div class="upe-upload-grid" style="grid-template-columns: minmax(0, 1fr);">

<div class="upe-upload-item<?= $hasSelfInitiatedTerminalReportDocument ? ' is-complete' : ''; ?>">

<?php if (
    $hasSelfInitiatedTerminalReportDocument
    &&
    $selfInitiatedTerminalReportDocument
): ?>

<div class="upe-upload-head">
<div>
<h5>Terminal Report</h5>
<p>Submitted terminal report.</p>
</div>
<span class="upe-upload-status">
<i class="bi bi-check-circle-fill"></i>
Uploaded
</span>
</div>

<div class="upe-complete-file">
<i class="bi bi-file-earmark-check-fill"></i>
<div>
<strong><?= e($selfInitiatedTerminalReportDocument['original_name']); ?></strong>
<small>
<?= in_array(strtolower((string)$selfInitiatedTerminalReportDocument['file_extension']), ['url', 'link'], true) ? 'External link' : e(researchFileSize((int)$selfInitiatedTerminalReportDocument['file_size'])); ?>
</small>
</div>
<a
    class="btn btn-sm btn-outline-success"
    href="<?= e(
        BASE_URL
        . '/research/view.php?id='
        . $researchId
        . '&document_id='
        . (int)$selfInitiatedTerminalReportDocument['document_id']
        . '&file_mode=view'
    ); ?>"
>
View
</a>
</div>

<?php elseif (
    ($research['workflow_status'] ?? '')
    === 'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED'
): ?>

<form
    method="POST"
    enctype="multipart/form-data"
    class="upe-upload-form"
>
<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>
<input
    type="hidden"
    name="action"
    value="upload_self_initiated_terminal_report"
>

<div>
<div class="rems-document-link-row">
    <input type="url" name="self_initiated_terminal_report_link" class="form-control rems-document-link-input"
           placeholder="https://drive.google.com/file/d/..." maxlength="255" data-research-document-link
           aria-label="Paste secure document link" required>
    <a href="#" class="btn btn-outline-asu rems-link-preview disabled"
       data-rems-view-link target="_blank" rel="noopener noreferrer" aria-disabled="true"
       aria-label="View document link" title="View link in a new tab">
        <i class="bi bi-eye" aria-hidden="true"></i>
    </a>
</div>
<div class="form-note rems-upload-view-note rems-upload-info-note" data-rems-view-note role="status" aria-live="polite">
Paste a shareable HTTPS link. You must click the eye button to view it before submitting.
</div>
</div>

<div>
<label class="form-label fw-semibold">Remarks <span class="text-muted fw-normal">(optional)</span></label>
<textarea
    name="self_initiated_terminal_remarks"
    class="form-control"
    rows="3"
    maxlength="5000"
    placeholder="Optional note about the Terminal Report"
></textarea>
</div>

<button
    type="submit"
    class="btn btn-asu btn-sm"
    disabled
    title="View the document link first"
>
<i class="bi bi-send-check me-1"></i>
Submit Terminal Report
</button>

</form>

<?php endif; ?>

</div>

</div>

</section>

<?php endif; ?>


<!-- =====================================================
     SELF-INITIATED - SYSTEM-GENERATED PERMIT
====================================================== -->

<?php if (
    $user['role'] === 'faculty'
    &&
    (int)$research['researcher_id'] === (int)$user['user_id']
    &&
    $selfInitiatedPermitAvailable
): ?>

<section class="workflow-card upe-step-card">

<div class="workflow-text">

<h4>
Self-Initiated Research — Permit to Publish / Present
</h4>

<p>
Your permit was generated automatically after the Terminal Report was submitted.
You may view, print, or save the permit as PDF.
</p>

</div>

<div class="upe-upload-grid" style="grid-template-columns: minmax(0, 1fr);">

<div class="upe-upload-item is-complete">

<div class="upe-upload-head">
<div>
<h5>System-Generated Permit</h5>
<p>Permit No.: <?= e($selfInitiatedPermitNumber); ?></p>
</div>

<span class="upe-upload-status">
<i class="bi bi-check-circle-fill"></i>
Generated
</span>
</div>

<div class="upe-complete-file">
<i class="bi bi-file-earmark-check-fill"></i>
<div>
<strong>Permit to Publish / Present</strong>
<small>
Generated <?= e(date('M d, Y', strtotime($selfInitiatedPermitIssuedAt))); ?>
</small>
</div>
<a
    class="btn btn-sm btn-outline-success"
    href="<?= e($selfInitiatedPermitUrl); ?>"
>
View Permit
</a>
</div>

</div>

</div>

</section>

<?php endif; ?>


<!-- =====================================================
     SELF-INITIATED - PUBLICATION UPLOAD
====================================================== -->

<?php if (
    $user['role'] === 'faculty'
    &&
    (int)$research['researcher_id'] === (int)$user['user_id']
    &&
    $selfInitiatedPermitAvailable
): ?>

<section class="workflow-card upe-step-card">

<div class="workflow-text">

<h4>
Self-Initiated Research — Publication
</h4>

<p>
Submit a shareable HTTPS link to the final publication or presentation proof.
</p>

</div>

<div class="upe-upload-grid" style="grid-template-columns: minmax(0, 1fr);">

<div class="upe-upload-item<?= $hasSelfInitiatedPublicationProofDocument ? ' is-complete' : ''; ?>">

<?php if ($hasSelfInitiatedPublicationProofDocument): ?>
<div class="upe-upload-head">
<div>
<h5>Publication File</h5>
<p>Submitted publication / presentation proof.</p>
</div>
<span class="upe-upload-status">
<i class="bi bi-check-circle-fill"></i>
Uploaded
</span>
</div>
<?php endif; ?>

<?php if (
    $hasSelfInitiatedPublicationProofDocument
    &&
    $selfInitiatedPublicationProofDocument
): ?>

<div class="upe-complete-file">
<i class="bi bi-file-earmark-check-fill"></i>
<div>
<strong><?= e($selfInitiatedPublicationProofDocument['original_name']); ?></strong>
<small>
<?= in_array(strtolower((string)$selfInitiatedPublicationProofDocument['file_extension']), ['url', 'link'], true) ? 'External link' : e(researchFileSize((int)$selfInitiatedPublicationProofDocument['file_size'])); ?>
</small>
</div>
<a
    class="btn btn-sm btn-outline-success"
    href="<?= e(
        BASE_URL
        . '/research/view.php?id='
        . $researchId
        . '&document_id='
        . (int)$selfInitiatedPublicationProofDocument['document_id']
        . '&file_mode=view'
    ); ?>"
>
View
</a>
</div>

<?php elseif (
    in_array(
        (string)($research['workflow_status'] ?? ''),
        [
            'SELF_INITIATED_TERMINAL_REPORT_SUBMITTED',
            'SELF_INITIATED_PERMIT_GENERATED'
        ],
        true
    )
): ?>

<form
    method="POST"
    enctype="multipart/form-data"
    class="upe-upload-form"
>
<input
    type="hidden"
    name="csrf_token"
    value="<?= e(generateCsrfToken()); ?>"
>
<input
    type="hidden"
    name="action"
    value="upload_self_initiated_publication_proof"
>

<div class="rems-document-link-row">
    <input type="url" name="self_initiated_publication_proof_link" class="form-control rems-document-link-input"
           placeholder="https://drive.google.com/file/d/..." maxlength="255" data-research-document-link
           aria-label="Paste secure document link" required>
    <a href="#" class="btn btn-outline-asu rems-link-preview disabled"
       data-rems-view-link target="_blank" rel="noopener noreferrer" aria-disabled="true"
       aria-label="View document link" title="View link in a new tab">
        <i class="bi bi-eye" aria-hidden="true"></i>
    </a>
</div>
<div class="form-note rems-upload-view-note rems-upload-info-note" data-rems-view-note role="status" aria-live="polite">
Paste a shareable HTTPS link. You must click the eye button to view it before submitting.
</div>

<button
    type="submit"
    class="btn btn-asu btn-sm"
    disabled
    title="View the document link first"
>
<i class="bi bi-cloud-arrow-up me-1"></i>
Upload Publication
</button>

</form>

<?php endif; ?>

</div>

</div>

</section>

<?php endif; ?>




<?php endif; ?>


<!-- =====================================================
     ADMIN - RPS WORKFLOW MONITORING
====================================================== -->

<?php if (
    $user['role'] === 'admin'
    &&
    $research['status'] !== 'Draft'
    &&
    isDetailedResearchWorkflow(
        $research
    )
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Research Processing Workflow Active

</h4>


<p>

This research record is now handled through
the detailed RPS workflow. The Administrator
may monitor the record, documents and history,
while processing actions are assigned to
Research Processing Staff.

</p>


</div>


</div>


</section>


<?php endif; ?>



<!-- =====================================================
     ADMIN SUBMITTED
====================================================== -->

<?php if (
    $user['role'] === 'admin'
    &&
    $research['status']
    === 'Submitted'
    &&
    !isDetailedResearchWorkflow(
        $research
    )
): ?>


<section class="workflow-card">


<div class="workflow-content">


<div class="workflow-text">


<h4>

Administrative Review

</h4>


<p>

This proposal has been formally
submitted and is ready for evaluation.

</p>


</div>



<form method="POST">


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
    value="start_review"
>


<button
    type="submit"
    class="btn btn-asu"
>

<i class="bi bi-clipboard-check me-1"></i>

Start Review

</button>


</form>


</div>


</section>


<?php endif; ?>



<!-- =====================================================
     ADMIN UNDER REVIEW
====================================================== -->

<?php if (
    $user['role'] === 'admin'
    &&
    $research['status']
    === 'Under Review'
    &&
    !isDetailedResearchWorkflow(
        $research
    )
): ?>


<section class="workflow-card">


<div class="workflow-text mb-3">


<h4>

Research Evaluation

</h4>


<p>

Enter your evaluation remarks
and select the appropriate decision.

</p>


</div>



<form
    method="POST"
    class="admin-review-form"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= e(
        generateCsrfToken()
    ); ?>"
>


<label
    for="admin_remarks"
    class="form-label"
>

Evaluation Remarks

</label>


<textarea
    id="admin_remarks"
    name="admin_remarks"
    class="form-control"
    maxlength="5000"
    placeholder="Enter evaluation findings, recommendations or required revisions."
    required
></textarea>



<div class="admin-review-actions">


<button
    type="submit"
    name="action"
    value="request_revision"
    class="btn btn-warning"
>

<i class="bi bi-arrow-repeat me-1"></i>

For Revision

</button>


<button
    type="submit"
    name="action"
    value="reject"
    class="btn btn-outline-danger"
>

<i class="bi bi-x-circle me-1"></i>

Reject

</button>


<button
    type="submit"
    name="action"
    value="approve"
    class="btn btn-asu"
>

<i class="bi bi-check-circle me-1"></i>

Approve

</button>


</div>


</form>


</section>


<?php endif; ?>



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


<?php endif; ?>


</div>


</main>


</div>



<!-- =========================================================
     RPS SECTION COMMENT MODAL
========================================================= -->

<?php if (
    ($user['role'] ?? '') === 'rps'
    &&
    !researchSectionRemarksAreLocked($research)
): ?>

<div
    class="modal fade"
    id="rpsSectionCommentModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <div class="modal-kicker">
                        Private RPS Comment
                    </div>

                    <h5 class="modal-title mb-0">
                        <span id="rpsSectionCommentActionLabel">Add Comment</span> —
                        <span id="rpsSectionCommentLabel">Research Section</span>
                    </h5>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>

            <div class="modal-body">

                <textarea
                    id="rpsSectionCommentText"
                    class="form-control"
                    rows="5"
                    maxlength="5000"
                    placeholder="Write a private comment for the researcher..."
                ></textarea>

                <div
                    id="rpsSectionCommentError"
                    class="invalid-feedback"
                >
                    Please enter a comment before saving.
                </div>

                <div class="section-comment-modal-note">
                    <i class="bi bi-info-circle"></i>
                    Click Save Comment to save it immediately. It will remain visible to the assigned researcher in later steps, without changing the workflow.
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
                    type="button"
                    class="btn btn-asu"
                    id="saveRpsSectionComment"
                >
                    <i class="bi bi-check2 me-1"></i>
                    Save Comment
                </button>

            </div>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const modalElement = document.getElementById('rpsSectionCommentModal');
    const textarea = document.getElementById('rpsSectionCommentText');
    const commentLabel = document.getElementById('rpsSectionCommentLabel');
    const actionLabel = document.getElementById('rpsSectionCommentActionLabel');
    const saveButton = document.getElementById('saveRpsSectionComment');
    const errorBox = document.getElementById('rpsSectionCommentError');
    const csrfToken = <?= json_encode(generateCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    if (!modalElement || !textarea || !commentLabel || !actionLabel || !saveButton) return;

    let activeSection = '';
    let activeButton = null;
    let saving = false;

    modalElement.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        activeButton = trigger;
        activeSection = trigger.getAttribute('data-section-key') || '';
        const existing = trigger.getAttribute('data-existing-comment') || '';
        commentLabel.textContent = trigger.getAttribute('data-section-label') || 'Research Section';
        actionLabel.textContent = existing.trim() ? 'Edit Comment' : 'Add Comment';
        textarea.value = existing;
        textarea.classList.remove('is-invalid');
        if (errorBox) {
            errorBox.textContent = 'Please enter a comment before saving.';
            errorBox.style.display = '';
        }
        window.setTimeout(function () { textarea.focus(); }, 180);
    });

    textarea.addEventListener('input', function () {
        textarea.classList.remove('is-invalid');
        if (errorBox) errorBox.style.display = '';
    });

    saveButton.addEventListener('click', async function () {
        if (saving) return;
        const value = textarea.value.trim();
        if (!activeSection || !value || value.length > 5000) {
            textarea.classList.add('is-invalid');
            if (errorBox) {
                errorBox.textContent = value.length > 5000
                    ? 'Each comment must not exceed 5,000 characters.'
                    : 'Please enter a comment before saving.';
                errorBox.style.display = 'block';
            }
            return;
        }

        saving = true;
        saveButton.disabled = true;
        const originalLabel = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Saving...';
        textarea.classList.remove('is-invalid');
        try {
            const payload = new URLSearchParams({
                action: 'save_section_comment',
                csrf_token: csrfToken,
                section_key: activeSection,
                remark_text: value
            });
            const response = await fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: payload.toString()
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to save the comment.');
            }

            // Only update the UI after the database has confirmed the save.
            if (activeButton) {
                activeButton.classList.add('has-saved-comment');
                activeButton.classList.remove('has-pending-comment');
                activeButton.setAttribute('data-existing-comment', value);
                const label = activeButton.querySelector('.section-comment-button-label');
                if (label) label.textContent = 'View 1 comment';
                const state = document.querySelector('[data-comment-state="' + activeSection + '"]');
                if (state) {
                    state.classList.remove('is-pending');
                    state.classList.add('is-saved');
                    state.innerHTML = '<i class="bi bi-check-circle"></i> Saved';
                }
            }
            bootstrap.Modal.getOrCreateInstance(modalElement).hide();
        } catch (error) {
            textarea.classList.add('is-invalid');
            if (errorBox) {
                errorBox.textContent = error.message || 'Unable to save the comment.';
                errorBox.style.display = 'block';
            }
        } finally {
            saving = false;
            saveButton.disabled = false;
            saveButton.innerHTML = originalLabel;
        }
    });
});
</script>

<?php endif; ?>


<!-- =========================================================
     READ-ONLY SAVED RPS COMMENT VIEWER
========================================================= -->

<?php if (
    ($user['role'] ?? '') === 'rps'
    ||
    (
        ($user['role'] ?? '') === 'faculty'
        &&
        (int)($research['researcher_id'] ?? 0) === (int)($user['user_id'] ?? 0)
    )
): ?>

<div
    class="modal fade"
    id="sectionRemarkViewModal"
    tabindex="-1"
    aria-labelledby="sectionRemarkViewHeading"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="sectionRemarkViewHeading">RPS Comment</h5>
                    <span class="rems-comment-subtitle" id="sectionRemarkViewLabel">Research Section</span>
                </div>
                <span class="rems-comment-private" title="Private RPS comment">
                    <i class="bi bi-lock" aria-hidden="true"></i> Private
                </span>
            </div>
            <div class="modal-body">
                <p id="sectionRemarkViewMessage" class="section-comment-view-message"></p>
            </div>
            <div class="modal-footer">
                <div class="section-comment-view-meta">
                    <span id="sectionRemarkViewAuthorWrap">
                        <span id="sectionRemarkViewAuthor"></span>
                    </span>
                    <span id="sectionRemarkViewDateWrap">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <span id="sectionRemarkViewDate"></span>
                    </span>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const viewModal =
        document.getElementById('sectionRemarkViewModal');

    if (!viewModal) {
        return;
    }

    const label = document.getElementById('sectionRemarkViewLabel');
    const message = document.getElementById('sectionRemarkViewMessage');
    const author = document.getElementById('sectionRemarkViewAuthor');
    const date = document.getElementById('sectionRemarkViewDate');
    const authorWrap = document.getElementById('sectionRemarkViewAuthorWrap');
    const dateWrap = document.getElementById('sectionRemarkViewDateWrap');

    viewModal.addEventListener('show.bs.modal', function (event) {

        const trigger = event.relatedTarget;

        if (!trigger) {
            return;
        }

        const sectionLabel =
            trigger.getAttribute('data-section-label') || 'Research Section';
        const commentText =
            trigger.getAttribute('data-comment-text') || '';
        const commentAuthor =
            trigger.getAttribute('data-comment-author') || '';
        const commentDate =
            trigger.getAttribute('data-comment-date') || '';

        label.textContent = sectionLabel;
        message.textContent = commentText;
        author.textContent = commentAuthor;
        date.textContent = commentDate;

        authorWrap.style.display = commentAuthor ? 'inline-flex' : 'none';
        dateWrap.style.display = commentDate ? 'inline-flex' : 'none';
    });
});
</script>

<?php endif; ?>


<!-- =========================================================
     SUBMIT DRAFT MODAL
========================================================= -->

<?php if (
    $user['role'] === 'faculty'
    &&
    $research['status']
    === 'Draft'
    &&
    $hasRequiredPhaseOneDocuments
): ?>


<div
    class="modal fade"
    id="submitModal"
    tabindex="-1"
>


<div class="
    modal-dialog
    modal-dialog-centered
">


<div class="modal-content">


<div class="modal-header">


<h5 class="modal-title">

Submit to RPS Processing

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

Submit this research manuscript to the
Research Office for RPS processing?

</p>


<div class="alert alert-warning mb-0">

Editing will temporarily be disabled
while the proposal is in the review process.

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

Confirm RPS Submission

</button>


</div>


</form>


</div>


</div>


</div>


<?php endif; ?>



<!-- =========================================================
     RESUBMIT MODAL
========================================================= -->

<?php if (
    $user['role'] === 'faculty'
    &&
    $research['status']
    === 'For Revision'
    &&
    $canManuallyResubmit
    && !in_array(($research['workflow_status'] ?? ''),
        ['URRC_REVISION_REQUIRED', 'INHOUSE_REVISION_REQUIRED'], true)
): ?>


<div
    class="modal fade"
    id="resubmitModal"
    tabindex="-1"
>


<div class="
    modal-dialog
    modal-dialog-centered
">


<div class="modal-content">


<div class="modal-header">


<h5 class="modal-title">

Resubmit Revised Proposal

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
    value="resubmit_revision"
>


<p>

<?php if (isDetailedResearchWorkflow($research)): ?>
Submit the revised proposal back to
Research Processing Staff for review?
<?php else: ?>
Submit the revised proposal back
to the Administrator for review?
<?php endif; ?>

</p>


<div class="alert alert-warning mb-0">

Make sure the requested revisions
have been addressed before resubmitting.

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

<i class="bi bi-arrow-repeat me-1"></i>

Confirm Resubmission

</button>


</div>


</form>


</div>


</div>


</div>


<?php endif; ?>



<!-- =========================================================
     SELF-INITIATED DOCUMENT LINK — MUST VIEW BEFORE SUBMIT
     Works for newly inserted AJAX workflow cards as well.
========================================================= -->
<script>
(function () {
    'use strict';
    function validatedHttpsLink(value) {
        const candidate = String(value || '').trim();
        if (!candidate || candidate.length > 255) return null;
        try {
            const url = new URL(candidate);
            const host = url.hostname.toLowerCase();
            const isIpAddress = /^(?:\d{1,3}\.){3}\d{1,3}$/.test(host) || host.includes(':');
            if (url.protocol !== 'https:' || !host || url.username || url.password ||
                isIpAddress || host === 'localhost' || host.endsWith('.localhost') ||
                host.endsWith('.local')) return null;
            return candidate;
        } catch (error) { return null; }
    }

    function actionName(form) {
        const field = form && form.querySelector('input[name="action"]');
        return field ? field.value : '';
    }

    function resetViewState(form) {
        if (!form || !form.classList.contains('upe-upload-form')) return;
        const input = form.querySelector('input[data-research-document-link]');
        const submit = form.querySelector('button[type="submit"]');
        const note = form.querySelector('[data-rems-view-note]');
        const eye = form.querySelector('[data-rems-view-link]');
        if (!input || !submit || !note || !eye) return;
        form.dataset.remsViewedUrl = '';
        submit.disabled = true;
        submit.title = 'View the document link first';
        note.textContent = 'Paste a shareable HTTPS link. You must click the eye button to view it before submitting.';
        note.classList.remove('is-viewed', 'is-error');
        eye.classList.remove('is-viewed');
    }

    function updateViewLink(input) {
        if (!input || !input.matches('input[data-research-document-link]')) return;
        const row = input.closest('.rems-document-link-row');
        const eye = row && row.querySelector('a[data-rems-view-link]');
        if (!eye) return;
        const url = validatedHttpsLink(input.value);
        eye.href = url || '#';
        eye.classList.toggle('disabled', !url);
        eye.setAttribute('aria-disabled', url ? 'false' : 'true');
        eye.tabIndex = url ? 0 : -1;
        const form = input.closest('form.upe-upload-form');
        if (form && form.dataset.remsViewedUrl !== (url || '')) {
            resetViewState(form);
        }
    }

    function syncVisibleViewLinks() {
        document.querySelectorAll('input[data-research-document-link]').forEach(updateViewLink);
        document.querySelectorAll('form.upe-upload-form').forEach(function (form) {
            if (form.querySelector('input[data-research-document-link]') &&
                !form.dataset.remsViewedUrl) resetViewState(form);
        });
    }

    // Delegated because the existing AJAX code replaces the next upload step.
    document.addEventListener('input', function (event) {
        updateViewLink(event.target);
    });
    document.addEventListener('change', function (event) {
        updateViewLink(event.target);
    });
    document.addEventListener('click', async function (event) {
        const eye = event.target.closest('a[data-rems-view-link]');
        if (!eye) return;
        const row = eye.closest('.rems-document-link-row');
        const input = row && row.querySelector('input[data-research-document-link]');
        const form = eye.closest('form.upe-upload-form');
        const url = input ? validatedHttpsLink(input.value) : null;
        if (!url || !form) {
            event.preventDefault();
            if (input) input.focus();
            return;
        }
        const action = actionName(form);
        const uploadField = input.name;
        const slotField = form.querySelector('input[name="upe_slot"]');
        const note = form.querySelector('[data-rems-view-note]');
        const submit = form.querySelector('button[type="submit"]');
        const csrf = form.querySelector('input[name="csrf_token"]');
        if (!note || !submit) return;
        eye.href = url; // Existing target=_blank automatically opens the URL.
        submit.disabled = true;
        eye.classList.remove('is-viewed');
        note.classList.remove('is-error','is-viewed');
        note.textContent = 'Opening the link and recording your view...';

        const data = new FormData();
        data.append('action', 'confirm_self_initiated_document_link_view');
        data.append('upload_action', action);
        data.append(uploadField, url);
        data.append('upe_slot', slotField ? slotField.value : '0');
        data.append('csrf_token', csrf ? csrf.value : '');
        try {
            const response = await fetch(window.location.href, {
                method: 'POST', body: data, credentials: 'same-origin',
                cache: 'no-store', headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to record your view.');
            }
            // Ignore a late response if the user changed the URL meanwhile.
            if (form.isConnected && input.value.trim() === url) {
                form.dataset.remsViewedUrl = url;
                submit.disabled = false;
                submit.title = '';
                eye.classList.add('is-viewed');
                note.classList.add('is-viewed');
                note.textContent = 'Link opened. You may now submit this document.';
            }
        } catch (error) {
            resetViewState(form);
            note.classList.add('is-error');
            note.textContent = error.message || 'Could not record the link visit. Please try again.';
        }
    });

    // Block both normal form submissions and the existing AJAX uploader
    // unless this specific form has a matching confirmed eye-button visit.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) ||
            !form.matches('form.upe-upload-form')) return;
        const input = form.querySelector('input[data-research-document-link]');
        if (!input) return;
        if (!form.dataset.remsViewedUrl ||
            form.dataset.remsViewedUrl !== input.value.trim()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            resetViewState(form);
            const note = form.querySelector('[data-rems-view-note]');
            if (note) {
                note.classList.add('is-error');
                note.textContent = 'You must click the eye button to view the link before submitting.';
            }
        }
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncVisibleViewLinks);
    } else {
        syncVisibleViewLinks();
    }
})();
</script>

<!-- =========================================================
     SELF-INITIATED FILE UPLOAD — NO PAGE RELOAD / NO BLINK
     UX ONLY:
     - Uses the existing PHP upload actions exactly as-is.
     - Keeps the user in the same visible workflow area.
     - Updates only the Self-Initiated upload-step cards.
     - Falls back to the normal form submit if AJAX is unavailable.
========================================================= -->
<script>
(function () {
    'use strict';

    const ajaxUploadActions = new Set([
        'upload_upe_certificate',
        'upload_self_initiated_completed_research',
        'upload_self_initiated_terminal_report',
        'upload_self_initiated_publication_proof'
    ]);

    let uploadInProgress = false;

    function normalizeText(value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getAction(form) {
        const actionField =
            form.querySelector('input[name="action"]');

        return actionField
            ? normalizeText(actionField.value)
            : '';
    }

    function getStepHeading(step) {
        if (!step) {
            return '';
        }

        const heading = step.querySelector(
            '.workflow-text h4, h4, h3, h5'
        );

        return heading
            ? normalizeText(heading.textContent)
            : '';
    }

    function getActiveStep(form) {
        return form.closest(
            'section.workflow-card.upe-step-card'
        );
    }

    function hasSelectedFile(form) {
        const linkField = form.querySelector('input[data-research-document-link]');
        if (!linkField) return false;
        try {
            const url = new URL(linkField.value.trim());
            return url.protocol === 'https:' && !!url.hostname && !url.username && !url.password;
        } catch (error) {
            return false;
        }
    }

    function buildFormData(form, submitter) {
        let data;

        try {
            data = new FormData(
                form,
                submitter || undefined
            );
        } catch (error) {
            data = new FormData(form);

            if (
                submitter &&
                submitter.name &&
                !data.has(submitter.name)
            ) {
                data.append(
                    submitter.name,
                    submitter.value || ''
                );
            }
        }

        return data;
    }

    function setUploadingState(button, isUploading) {
        if (!button) {
            return;
        }

        if (isUploading) {
            if (!button.dataset.remsOriginalHtml) {
                button.dataset.remsOriginalHtml =
                    button.innerHTML;
            }

            button.disabled = true;
            button.setAttribute(
                'aria-busy',
                'true'
            );

            button.innerHTML =
                '<span class="spinner-border spinner-border-sm" ' +
                'aria-hidden="true"></span>' +
                '<span>Uploading...</span>';

            return;
        }

        button.disabled = false;
        button.removeAttribute('aria-busy');

        if (button.dataset.remsOriginalHtml) {
            button.innerHTML =
                button.dataset.remsOriginalHtml;

            delete button.dataset.remsOriginalHtml;
        }
    }

    function currentResearchId(url) {
        try {
            return new URL(
                url,
                window.location.href
            ).searchParams.get('id') || '';
        } catch (error) {
            return '';
        }
    }

    function isSameResearchPage(responseUrl) {
        try {
            const responseLocation =
                new URL(
                    responseUrl,
                    window.location.href
                );

            return (
                responseLocation.pathname ===
                    window.location.pathname
                &&
                currentResearchId(responseLocation.href) ===
                    currentResearchId(window.location.href)
            );
        } catch (error) {
            return true;
        }
    }

    function findStepByHeading(root, headingText) {
        const steps = root.querySelectorAll(
            'section.workflow-card.upe-step-card'
        );

        for (const step of steps) {
            if (
                getStepHeading(step) === headingText
            ) {
                return step;
            }
        }

        return null;
    }

    function syncSelfInitiatedSteps(
        responseDocument,
        activeHeading,
        savedScrollY
    ) {
        const currentSteps = Array.from(
            document.querySelectorAll(
                'section.workflow-card.upe-step-card'
            )
        );

        const responseSteps = Array.from(
            responseDocument.querySelectorAll(
                'section.workflow-card.upe-step-card'
            )
        );

        if (
            currentSteps.length === 0 ||
            responseSteps.length === 0
        ) {
            return false;
        }

        const currentIndex =
            currentSteps.findIndex(function (step) {
                return (
                    getStepHeading(step) ===
                    activeHeading
                );
            });

        const responseIndex =
            responseSteps.findIndex(function (step) {
                return (
                    getStepHeading(step) ===
                    activeHeading
                );
            });

        if (
            currentIndex < 0 ||
            responseIndex < 0
        ) {
            return false;
        }

        /*
         * IMPORTANT:
         * Leave every completed step ABOVE the current upload step
         * completely untouched.
         *
         * We only refresh the active step and the steps below it.
         * Because nothing above the user's viewport is replaced,
         * the page does not jump when the upload succeeds.
         */
        const firstCurrentToReplace =
            currentSteps[currentIndex];

        const currentTail =
            currentSteps.slice(currentIndex);

        const responseTail =
            responseSteps.slice(responseIndex);

        const htmlElement =
            document.documentElement;

        const bodyElement =
            document.body;

        const previousHtmlScrollBehavior =
            htmlElement.style.scrollBehavior;

        const previousBodyScrollBehavior =
            bodyElement.style.scrollBehavior;

        const previousHtmlOverflowAnchor =
            htmlElement.style.overflowAnchor;

        const previousBodyOverflowAnchor =
            bodyElement.style.overflowAnchor;

        /*
         * Disable browser scroll anchoring while the upload cards
         * are swapped. This prevents Chrome from trying to "help"
         * by moving the viewport when the focused submit button
         * disappears from the DOM.
         */
        htmlElement.style.scrollBehavior = 'auto';
        bodyElement.style.scrollBehavior = 'auto';
        htmlElement.style.overflowAnchor = 'none';
        bodyElement.style.overflowAnchor = 'none';

        const fragment =
            document.createDocumentFragment();

        responseTail.forEach(function (step) {
            fragment.appendChild(
                document.importNode(step, true)
            );
        });

        firstCurrentToReplace.before(fragment);

        currentTail.forEach(function (step) {
            step.remove();
        });

        /*
         * Freeze the viewport at the exact same document position
         * it had when the user pressed Upload.
         *
         * This is NOT a scroll-to-section behavior and there is no
         * smooth animation. It only prevents the browser from moving
         * away from the user's current area during the DOM refresh.
         */
        window.scrollTo(
            0,
            Math.max(
                0,
                Number(savedScrollY) || 0
            )
        );

        requestAnimationFrame(function () {
            window.scrollTo(
                0,
                Math.max(
                    0,
                    Number(savedScrollY) || 0
                )
            );

            requestAnimationFrame(function () {
                htmlElement.style.scrollBehavior =
                    previousHtmlScrollBehavior;

                bodyElement.style.scrollBehavior =
                    previousBodyScrollBehavior;

                htmlElement.style.overflowAnchor =
                    previousHtmlOverflowAnchor;

                bodyElement.style.overflowAnchor =
                    previousBodyOverflowAnchor;
            });
        });

        return true;
    }

    function extractInlineError(
        responseDocument
    ) {
        const selectors = [
            '.alert-danger',
            '.alert-warning',
            '.is-invalid + .invalid-feedback'
        ];

        for (const selector of selectors) {
            const element =
                responseDocument
                    .querySelector(selector);

            if (element) {
                const message =
                    normalizeText(
                        element.textContent
                    );

                if (message) {
                    return message;
                }
            }
        }

        return '';
    }

    function showInlineUploadError(
        step,
        message
    ) {
        if (!step || !message) {
            return;
        }

        const existing =
            step.querySelector(
                '.rems-inline-upload-error'
            );

        if (existing) {
            existing.remove();
        }

        const errorBox =
            document.createElement('div');

        errorBox.className =
            'alert alert-danger rems-inline-upload-error';

        errorBox.setAttribute(
            'role',
            'alert'
        );

        errorBox.textContent = message;

        const workflowText =
            step.querySelector(
                '.workflow-text'
            );

        if (workflowText) {
            workflowText.insertAdjacentElement(
                'afterend',
                errorBox
            );
        } else {
            step.prepend(errorBox);
        }
    }

    function normalSubmitFallback(
        form,
        submitter
    ) {
        form.dataset.remsNormalSubmit = '1';

        if (
            typeof form.requestSubmit === 'function'
        ) {
            form.requestSubmit(
                submitter || undefined
            );
        } else {
            HTMLFormElement.prototype.submit.call(form);
        }
    }

    document.addEventListener(
        'submit',
        async function (event) {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (
                form.dataset.remsNormalSubmit === '1'
            ) {
                delete form.dataset.remsNormalSubmit;
                return;
            }

            const action =
                getAction(form);

            if (
                !ajaxUploadActions.has(action) ||
                !hasSelectedFile(form)
            ) {
                return;
            }

            /*
             * Only the four existing Self-Initiated file upload
             * actions are handled here.
             */
            event.preventDefault();

            if (uploadInProgress) {
                return;
            }

            const activeStep =
                getActiveStep(form);

            if (!activeStep) {
                normalSubmitFallback(
                    form,
                    event.submitter
                );
                return;
            }

            const activeHeading =
                getStepHeading(activeStep);

            /*
             * Freeze the exact page position the user is currently
             * viewing. No section scrolling is performed after upload.
             */
            const savedScrollY =
                window.scrollY ||
                window.pageYOffset ||
                0;

            const submitter =
                event.submitter ||
                form.querySelector(
                    'button[type="submit"], input[type="submit"]'
                );

            uploadInProgress = true;
            setUploadingState(
                submitter,
                true
            );

            try {
                /*
                 * IMPORTANT:
                 * Do not use form.action here. This form contains an
                 * input named "action", which can shadow the native
                 * HTMLFormElement.action property and become an
                 * HTMLInputElement ("[object HTMLInputElement]").
                 */
                const formActionAttribute =
                    form.getAttribute('action');

                const requestUrl =
                    formActionAttribute &&
                    formActionAttribute.trim() !== ''
                        ? new URL(
                            formActionAttribute,
                            window.location.href
                        ).href
                        : window.location.href;

                const response =
                    await fetch(
                        requestUrl,
                        {
                            method:
                                (form.method || 'POST')
                                    .toUpperCase(),
                            body:
                                buildFormData(
                                    form,
                                    submitter
                                ),
                            credentials: 'same-origin',
                            redirect: 'follow',
                            headers: {
                                'X-Requested-With':
                                    'XMLHttpRequest'
                            }
                        }
                    );

                /*
                 * If PHP intentionally redirects somewhere other than
                 * this research page, preserve that existing flow.
                 */
                if (
                    response.url &&
                    !isSameResearchPage(
                        response.url
                    )
                ) {
                    window.location.assign(
                        response.url
                    );
                    return;
                }

                if (!response.ok) {
                    throw new Error(
                        'Upload request failed.'
                    );
                }

                const html =
                    await response.text();

                const responseDocument =
                    new DOMParser()
                        .parseFromString(
                            html,
                            'text/html'
                        );

                const inlineError =
                    extractInlineError(
                        responseDocument
                    );

                if (inlineError) {
                    showInlineUploadError(
                        activeStep,
                        inlineError
                    );

                    setUploadingState(
                        submitter,
                        false
                    );

                    uploadInProgress = false;
                    return;
                }

                const updated =
                    syncSelfInitiatedSteps(
                        responseDocument,
                        activeHeading,
                        savedScrollY
                    );

                if (!updated) {
                    /*
                     * Defensive fallback only. Normal behavior stays
                     * exactly the same if the returned page structure
                     * cannot be synchronized.
                     */
                    normalSubmitFallback(
                        form,
                        submitter
                    );

                    return;
                }

                /*
                 * No page reload, no jump to top, and no scroll-to-section.
                 * The viewport stays at the same position while the uploaded
                 * card and newly unlocked next step come from the real PHP response.
                 */
                uploadInProgress = false;

            } catch (error) {
                setUploadingState(
                    submitter,
                    false
                );

                uploadInProgress = false;

                normalSubmitFallback(
                    form,
                    submitter
                );
            }
        },
        true
    );
})();
</script>


<?php

require __DIR__ . '/../includes/footer.php';

?>