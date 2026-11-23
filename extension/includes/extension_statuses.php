
<?php
/**
 * ASU REMS - Extension Module
 *
 * File: extension/includes/extension_statuses.php
 *
 * Centralized Extension proposal workflow statuses.
 * Based on Section 4.1 of the Extension Services Manual.
 *
 * Independent from the Research Module.
 */

/*
|--------------------------------------------------------------------------
| EXTENSION PROPOSAL STATUS CONSTANTS
|--------------------------------------------------------------------------
*/

const EXT_STATUS_DRAFT = 'Draft';

const EXT_STATUS_SUBMITTED = 'Submitted';

const EXT_STATUS_UERC_REVIEW = 'UERC Technical Review';

const EXT_STATUS_UERC_REVISION = 'For UERC Revision';

// Already used by ESO review.php; formally register it in the shared flow.
const EXT_STATUS_PRE_INHOUSE_ENDORSEMENT = 'For Pre-In-House Endorsement';

const EXT_STATUS_INHOUSE_REVIEW = 'University In-House Review';

const EXT_STATUS_INHOUSE_REVISION = 'For In-House Revision';

const EXT_STATUS_ENDORSEMENT = 'For Endorsement';

const EXT_STATUS_LADCO_APPROVAL = 'For LADCO/ADCO Approval';

const EXT_STATUS_BOR_NOTATION = 'For BOR Notation';

const EXT_STATUS_CERTIFICATION = 'For Certification';

const EXT_STATUS_APPROVED = 'Approved';

/*
|--------------------------------------------------------------------------
| EXISTING EXTENSION STATUSES
|--------------------------------------------------------------------------
|
| Retained for compatibility with existing records.
| These are not additional Section 4.1 review stages.
|
*/

const EXT_STATUS_UNDER_REVIEW = 'Under Review';

const EXT_STATUS_FOR_REVISION = 'For Revision';

const EXT_STATUS_ONGOING = 'Ongoing';

const EXT_STATUS_COMPLETED = 'Completed';

const EXT_STATUS_REJECTED = 'Rejected';

const EXT_STATUS_ARCHIVED = 'Archived';

/*
|--------------------------------------------------------------------------
| PROPOSAL WORKFLOW STATUSES
|--------------------------------------------------------------------------
*/

function extensionProposalStatuses(): array
{
    return [
        EXT_STATUS_DRAFT,
        EXT_STATUS_SUBMITTED,
        EXT_STATUS_UERC_REVIEW,
        EXT_STATUS_UERC_REVISION,
        EXT_STATUS_PRE_INHOUSE_ENDORSEMENT,
        EXT_STATUS_INHOUSE_REVIEW,
        EXT_STATUS_INHOUSE_REVISION,
        EXT_STATUS_ENDORSEMENT,
        EXT_STATUS_LADCO_APPROVAL,
        EXT_STATUS_CERTIFICATION,
        EXT_STATUS_APPROVED
    ];
}

/*
|--------------------------------------------------------------------------
| ALL RECOGNIZED EXTENSION STATUSES
|--------------------------------------------------------------------------
*/

function extensionAllStatuses(): array
{
    return array_values(
        array_unique(
            array_merge(
                extensionProposalStatuses(),
                [
                    // Keep older BOR-only records valid without exposing a new stage.
                    EXT_STATUS_BOR_NOTATION,
                    EXT_STATUS_UNDER_REVIEW,
                    EXT_STATUS_FOR_REVISION,
                    EXT_STATUS_ONGOING,
                    EXT_STATUS_COMPLETED,
                    EXT_STATUS_REJECTED,
                    EXT_STATUS_ARCHIVED
                ]
            )
        )
    );
}

/*
|--------------------------------------------------------------------------
| VALIDATE STATUS
|--------------------------------------------------------------------------
*/

function extensionIsValidStatus(string $status): bool
{
    return in_array(
        $status,
        extensionAllStatuses(),
        true
    );
}

/*
|--------------------------------------------------------------------------
| STATUS LABEL
|--------------------------------------------------------------------------
*/

function extensionStatusLabel(string $status): string
{
    $labels = [
        EXT_STATUS_DRAFT => 'Draft',

        EXT_STATUS_SUBMITTED => 'Submitted',

        EXT_STATUS_UERC_REVIEW =>
            'UERC Technical Review',

        EXT_STATUS_UERC_REVISION =>
            'For UERC Revision',

        EXT_STATUS_PRE_INHOUSE_ENDORSEMENT =>
            'For Pre-In-House Endorsement',

        EXT_STATUS_INHOUSE_REVIEW =>
            'University In-House Review',

        EXT_STATUS_INHOUSE_REVISION =>
            'For In-House Revision',

        EXT_STATUS_ENDORSEMENT =>
            'For Endorsement',

        EXT_STATUS_LADCO_APPROVAL =>
            'For LADCO/UADCO/BOR',

        EXT_STATUS_BOR_NOTATION =>
            'For LADCO/UADCO/BOR',

        EXT_STATUS_CERTIFICATION =>
            'For Certification',

        EXT_STATUS_APPROVED =>
            'Approved',

        EXT_STATUS_UNDER_REVIEW =>
            'Under Review',

        EXT_STATUS_FOR_REVISION =>
            'For Revision',

        EXT_STATUS_ONGOING =>
            'Ongoing',

        EXT_STATUS_COMPLETED =>
            'Completed',

        EXT_STATUS_REJECTED =>
            'Rejected',

        EXT_STATUS_ARCHIVED =>
            'Archived'
    ];

    return $labels[$status] ?? $status;
}

/*
|--------------------------------------------------------------------------
| RESPONSIBLE ROLE FOR EACH STATUS
|--------------------------------------------------------------------------
|
| The Extension Service Office records official
| results of university committees and approving
| bodies. It does not replace their authority.
|
*/

function extensionStatusResponsibleRole(
    string $status
): ?string {

    $proponentStatuses = [
        EXT_STATUS_DRAFT,
        EXT_STATUS_SUBMITTED,
        EXT_STATUS_UERC_REVISION,
        EXT_STATUS_PRE_INHOUSE_ENDORSEMENT,
        EXT_STATUS_INHOUSE_REVISION
    ];

    $officeStatuses = [
        EXT_STATUS_UERC_REVIEW,
        EXT_STATUS_INHOUSE_REVIEW,
        EXT_STATUS_ENDORSEMENT,
        EXT_STATUS_LADCO_APPROVAL,
        EXT_STATUS_BOR_NOTATION,
        EXT_STATUS_CERTIFICATION,
        EXT_STATUS_APPROVED
    ];

    if (
        in_array(
            $status,
            $proponentStatuses,
            true
        )
    ) {
        return 'proponent';
    }

    if (
        in_array(
            $status,
            $officeStatuses,
            true
        )
    ) {
        return 'office';
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| PROPOSAL PROCESSING STATUS
|--------------------------------------------------------------------------
*/

function extensionIsProposalInProcess(
    string $status
): bool {

    return in_array(
        $status,
        [
            EXT_STATUS_SUBMITTED,
            EXT_STATUS_UERC_REVIEW,
            EXT_STATUS_UERC_REVISION,
            EXT_STATUS_PRE_INHOUSE_ENDORSEMENT,
            EXT_STATUS_INHOUSE_REVIEW,
            EXT_STATUS_INHOUSE_REVISION,
            EXT_STATUS_ENDORSEMENT,
            EXT_STATUS_LADCO_APPROVAL,
            EXT_STATUS_BOR_NOTATION,
            EXT_STATUS_CERTIFICATION
        ],
        true
    );
}

/*
|--------------------------------------------------------------------------
| APPROVED PROPOSAL
|--------------------------------------------------------------------------
|
| Approved means the proposal approval process
| is finished, not that the extension project
| itself has been completed.
|
*/

function extensionIsProposalApproved(
    string $status
): bool {

    return $status === EXT_STATUS_APPROVED;
}

/*
|--------------------------------------------------------------------------
| INACTIVE EXTENSION STATUS
|--------------------------------------------------------------------------
*/

function extensionIsInactiveStatus(
    string $status
): bool {

    return in_array(
        $status,
        [
            EXT_STATUS_REJECTED,
            EXT_STATUS_ARCHIVED,
            EXT_STATUS_COMPLETED
        ],
        true
    );
}
