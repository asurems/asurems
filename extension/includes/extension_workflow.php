
<?php
/**
 * ASU REMS - Extension Module
 *
 * File: extension/includes/extension_workflow.php
 *
 * Extension-only proposal workflow rules.
 * Based on Section 4.1 of the Extension Services Manual.
 *
 * Does not modify the Research Module.
 */

require_once __DIR__ . '/extension_roles.php';
require_once __DIR__ . '/extension_statuses.php';

/*
|--------------------------------------------------------------------------
| EXTENSION WORKFLOW TRANSITIONS
|--------------------------------------------------------------------------
|
| Defines the allowed movement of Extension proposals
| from one processing stage to another.
|
| The Extension Service Office records the official
| decisions of UERC, In-House reviewers, LADCO/ADCO,
| and BOR. It does not replace their authority.
|
| No automatic rejection, archiving, or revision
| deadlines are applied.
|
*/

function extensionWorkflowTransitions(): array
{
    return [

        /*
        |--------------------------------------------------------------
        | PROPOSAL SUBMISSION
        |--------------------------------------------------------------
        */

        EXT_STATUS_DRAFT => [
            EXT_STATUS_SUBMITTED
        ],

        /*
        |--------------------------------------------------------------
        | UERC TECHNICAL REVIEW
        |--------------------------------------------------------------
        */

        EXT_STATUS_SUBMITTED => [
            EXT_STATUS_UERC_REVIEW
        ],

        EXT_STATUS_UERC_REVIEW => [
            // Coordinator endorses the reviewed paper before In-House Review.
            EXT_STATUS_PRE_INHOUSE_ENDORSEMENT,
            EXT_STATUS_UERC_REVISION
        ],

        /*
        |--------------------------------------------------------------
        | REVISION AFTER UERC REVIEW
        |--------------------------------------------------------------
        */

        EXT_STATUS_UERC_REVISION => [
            EXT_STATUS_PRE_INHOUSE_ENDORSEMENT
        ],

        // ESO verifies the Coordinator's endorsement before starting In-House Review.
        EXT_STATUS_PRE_INHOUSE_ENDORSEMENT => [
            EXT_STATUS_INHOUSE_REVIEW
        ],

        /*
        |--------------------------------------------------------------
        | UNIVERSITY IN-HOUSE REVIEW
        |--------------------------------------------------------------
        */

        EXT_STATUS_INHOUSE_REVIEW => [
            // Accepted: move directly to Review and Endorsement.
            EXT_STATUS_ENDORSEMENT,
            // Accepted with Revision: proponent revises before endorsement.
            EXT_STATUS_INHOUSE_REVISION
        ],

        /*
        |--------------------------------------------------------------
        | REVISION AFTER IN-HOUSE REVIEW
        |--------------------------------------------------------------
        */

        EXT_STATUS_INHOUSE_REVISION => [
            EXT_STATUS_ENDORSEMENT
        ],

        /*
        |--------------------------------------------------------------
        | REVIEW AND ENDORSEMENT
        |--------------------------------------------------------------
        */

        EXT_STATUS_ENDORSEMENT => [
            EXT_STATUS_LADCO_APPROVAL
        ],

        /*
        |--------------------------------------------------------------
        | COMBINED LADCO / UADCO / BOR (one ESO processing submission)
        |--------------------------------------------------------------
        */

        EXT_STATUS_LADCO_APPROVAL => [
            EXT_STATUS_CERTIFICATION
        ],

        // Existing proposals that already reached the previous separate
        // BOR-notation status can still finish without data migration.
        EXT_STATUS_BOR_NOTATION => [
            EXT_STATUS_CERTIFICATION
        ],

        /*
        |--------------------------------------------------------------
        | NOTICE OF APPROVAL / CERTIFICATION
        |--------------------------------------------------------------
        */

        EXT_STATUS_CERTIFICATION => [
            EXT_STATUS_APPROVED
        ],

        /*
        |--------------------------------------------------------------
        | APPROVED PROPOSAL
        |--------------------------------------------------------------
        */

        EXT_STATUS_APPROVED => [],

        /*
        |--------------------------------------------------------------
        | EXISTING LEGACY STATUSES
        |--------------------------------------------------------------
        |
        | Retained for compatibility with existing records.
        | Their transitions have not been defined yet.
        |
        */

        EXT_STATUS_UNDER_REVIEW => [],

        EXT_STATUS_FOR_REVISION => [],

        EXT_STATUS_ONGOING => [],

        EXT_STATUS_COMPLETED => [],

        EXT_STATUS_REJECTED => [],

        EXT_STATUS_ARCHIVED => []

    ];
}

/*
|--------------------------------------------------------------------------
| GET NEXT STATUSES
|--------------------------------------------------------------------------
*/

function extensionNextStatuses(
    string $currentStatus
): array {

    $transitions = extensionWorkflowTransitions();

    return $transitions[$currentStatus] ?? [];
}

/*
|--------------------------------------------------------------------------
| CHECK IF TRANSITION IS ALLOWED
|--------------------------------------------------------------------------
*/

function extensionCanTransition(
    string $currentStatus,
    string $nextStatus
): bool {

    return in_array(
        $nextStatus,
        extensionNextStatuses($currentStatus),
        true
    );
}

/*
|--------------------------------------------------------------------------
| RESPONSIBLE ACTOR FOR EACH TRANSITION
|--------------------------------------------------------------------------
|
| proponent = Faculty / Extensionist / Extension Coordinator
| office    = Extension Service Office
|
*/

function extensionTransitionActor(
    string $currentStatus,
    string $nextStatus
): ?string {

    $actors = [

        EXT_STATUS_DRAFT => [
            EXT_STATUS_SUBMITTED => 'proponent'
        ],

        EXT_STATUS_SUBMITTED => [
            EXT_STATUS_UERC_REVIEW => 'office'
        ],

        EXT_STATUS_UERC_REVIEW => [
            EXT_STATUS_PRE_INHOUSE_ENDORSEMENT => 'office',
            EXT_STATUS_UERC_REVISION => 'office'
        ],

        EXT_STATUS_UERC_REVISION => [
            EXT_STATUS_PRE_INHOUSE_ENDORSEMENT => 'proponent'
        ],

        EXT_STATUS_PRE_INHOUSE_ENDORSEMENT => [
            EXT_STATUS_INHOUSE_REVIEW => 'office'
        ],

        EXT_STATUS_INHOUSE_REVIEW => [
            EXT_STATUS_ENDORSEMENT => 'office',
            EXT_STATUS_INHOUSE_REVISION => 'office'
        ],

        EXT_STATUS_INHOUSE_REVISION => [
            EXT_STATUS_ENDORSEMENT => 'proponent'
        ],

        EXT_STATUS_ENDORSEMENT => [
            EXT_STATUS_LADCO_APPROVAL => 'office'
        ],

        EXT_STATUS_LADCO_APPROVAL => [
            EXT_STATUS_CERTIFICATION => 'office'
        ],

        EXT_STATUS_BOR_NOTATION => [
            EXT_STATUS_CERTIFICATION => 'office'
        ],

        EXT_STATUS_CERTIFICATION => [
            EXT_STATUS_APPROVED => 'office'
        ]

    ];

    return $actors[$currentStatus][$nextStatus] ?? null;
}

/*
|--------------------------------------------------------------------------
| CHECK ROLE PERMISSION FOR TRANSITION
|--------------------------------------------------------------------------
*/

function extensionCanRoleTransition(
    string $currentStatus,
    string $nextStatus,
    ?string $role = null
): bool {

    if (
        !extensionCanTransition(
            $currentStatus,
            $nextStatus
        )
    ) {
        return false;
    }

    $actor = extensionTransitionActor(
        $currentStatus,
        $nextStatus
    );

    if ($actor === 'proponent') {

        return extensionIsCoordinator($role);
    }

    if ($actor === 'office') {

        return extensionIsOffice($role);
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| AVAILABLE TRANSITIONS FOR CURRENT ROLE
|--------------------------------------------------------------------------
*/

function extensionAvailableTransitions(
    string $currentStatus,
    ?string $role = null
): array {

    $available = [];

    foreach (
        extensionNextStatuses($currentStatus)
        as $nextStatus
    ) {

        if (
            extensionCanRoleTransition(
                $currentStatus,
                $nextStatus,
                $role
            )
        ) {

            $available[] = $nextStatus;
        }
    }

    return $available;
}

/*
|--------------------------------------------------------------------------
| INITIAL PROPOSAL SUBMISSION
|--------------------------------------------------------------------------
*/

function extensionCanSubmitDraft(
    string $currentStatus,
    ?string $role = null
): bool {

    return extensionCanRoleTransition(
        $currentStatus,
        EXT_STATUS_SUBMITTED,
        $role
    );
}

/*
|--------------------------------------------------------------------------
| UERC REVISION SUBMISSION
|--------------------------------------------------------------------------
*/

function extensionCanSubmitUercRevision(
    string $currentStatus,
    ?string $role = null
): bool {

    return extensionCanRoleTransition(
        $currentStatus,
        EXT_STATUS_PRE_INHOUSE_ENDORSEMENT,
        $role
    );
}

/*
|--------------------------------------------------------------------------
| IN-HOUSE REVISION SUBMISSION
|--------------------------------------------------------------------------
*/

function extensionCanSubmitInhouseRevision(
    string $currentStatus,
    ?string $role = null
): bool {

    return extensionCanRoleTransition(
        $currentStatus,
        EXT_STATUS_ENDORSEMENT,
        $role
    );
}

/*
|--------------------------------------------------------------------------
| VALIDATE EXTENSION WORKFLOW TRANSITION
|--------------------------------------------------------------------------
|
| This function only validates the action.
| It does not update database records.
|
*/

function extensionValidateTransition(
    string $currentStatus,
    string $nextStatus,
    ?string $role = null
): array {

    if (!extensionIsValidStatus($currentStatus)) {

        return [
            'valid' => false,
            'message' =>
                'Invalid current Extension status.'
        ];
    }

    if (!extensionIsValidStatus($nextStatus)) {

        return [
            'valid' => false,
            'message' =>
                'Invalid next Extension status.'
        ];
    }

    if (
        !extensionCanTransition(
            $currentStatus,
            $nextStatus
        )
    ) {

        return [
            'valid' => false,
            'message' =>
                'This Extension workflow transition is not allowed.'
        ];
    }

    if (
        !extensionCanRoleTransition(
            $currentStatus,
            $nextStatus,
            $role
        )
    ) {

        return [
            'valid' => false,
            'message' =>
                'You do not have permission to perform this Extension action.'
        ];
    }

    return [
        'valid' => true,
        'message' =>
            'Extension workflow transition is allowed.'
    ];
}

/*
|--------------------------------------------------------------------------
| COORDINATOR ENDORSEMENT DOCUMENTS
|--------------------------------------------------------------------------
| Document links must be submitted by the actual owning Coordinator. The
| previous status-history-only marker is deliberately not sufficient.
| Requires extension_coordinator_endorsements.sql (Extension-only).
*/
function extensionCoordinatorEndorsementMarker(string $status): ?string
{
    if ($status === EXT_STATUS_PRE_INHOUSE_ENDORSEMENT) {
        return 'COORDINATOR_INHOUSE_ENDORSED';
    }
    if ($status === EXT_STATUS_ENDORSEMENT) {
        return 'COORDINATOR_FINAL_ENDORSED';
    }
    return null;
}

function extensionCoordinatorEndorsementStage(string $status): ?string
{
    return match ($status) {
        EXT_STATUS_PRE_INHOUSE_ENDORSEMENT => 'pre_inhouse',
        EXT_STATUS_ENDORSEMENT => 'final',
        default => null,
    };
}

function extensionCoordinatorValidEndorsementUrl(string $url): bool
{
    if ($url === '' || strlen($url) > 1000 || preg_match('/\s/', $url)
        || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return false;
    $host = strtolower((string)$parts['host']);
    return $host !== 'localhost' && !str_ends_with($host, '.localhost')
        && !str_ends_with($host, '.local') && !str_ends_with($host, '.internal')
        && !filter_var($host, FILTER_VALIDATE_IP);
}

function extensionCoordinatorEndorsementRecord(
    PDO $pdo, int $extensionId, int $coordinatorId, string $status
): array|false {
    $stage = extensionCoordinatorEndorsementStage($status);
    if ($stage === null || $extensionId < 1 || $coordinatorId < 1) return false;
    $stmt = $pdo->prepare(
        'SELECT endorsement_id, document_url, endorsement_date, remarks, submitted_at
           FROM extension_coordinator_endorsements
          WHERE extension_id = ? AND coordinator_id = ? AND stage = ?
          LIMIT 1'
    );
    $stmt->execute([$extensionId, $coordinatorId, $stage]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && extensionCoordinatorValidEndorsementUrl((string)$row['document_url'])
        ? $row : false;
}

function extensionCoordinatorHasEndorsed(
    PDO $pdo, int $extensionId, int $coordinatorId, string $status
): bool {
    return (bool)extensionCoordinatorEndorsementRecord($pdo, $extensionId, $coordinatorId, $status);
}
