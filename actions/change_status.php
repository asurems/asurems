<?php

/**
 * ============================================================
 * ASU-REMS
 * CHANGE RESEARCH STATUS ACTION
 * ============================================================
 *
 * Handles normal workflow transitions such as:
 *
 * DRAFT -> SUBMITTED
 * SUBMITTED -> FOR_ETHICS_REVIEW
 * ETHICS_COMPLETED -> FOR_URRC
 * READY_FOR_NTP -> NTP_ISSUED
 *
 * Review results such as UREC, URRC and In-House
 * will have their own separate action files.
 */


/* ============================================================
   SESSION
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   REQUIRED FILES
   ============================================================ */

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/research_statuses.php';
require_once __DIR__ . '/../services/ResearchService.php';


/* ============================================================
   ONLY POST REQUESTS ARE ALLOWED
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   CSRF SECURITY CHECK
   ============================================================ */

require_csrf();


/* ============================================================
   GET LOGGED-IN USER
   ============================================================ */

/*
 * Supports these common session formats:
 *
 * $_SESSION['user_id']
 *
 * OR
 *
 * $_SESSION['user']['id']
 */

$userId = 0;


if (!empty($_SESSION['user_id'])) {

    $userId =
        (int) $_SESSION['user_id'];

} elseif (
    !empty($_SESSION['user']) &&
    !empty($_SESSION['user']['id'])
) {

    $userId =
        (int) $_SESSION['user']['id'];
}


/* ============================================================
   USER MUST BE LOGGED IN
   ============================================================ */

if ($userId <= 0) {

    $_SESSION['error_message'] =
        'Please log in before performing this action.';

    header('Location: ../login.php');
    exit;
}


/* ============================================================
   GET USER ROLE
   ============================================================ */

/*
 * Flexible muna dahil hindi pa natin nakikita
 * ang exact session structure ng login system ninyo.
 */

$userRole = '';


if (!empty($_SESSION['role'])) {

    $userRole =
        strtolower(
            trim(
                (string) $_SESSION['role']
            )
        );

} elseif (
    !empty($_SESSION['user']) &&
    !empty($_SESSION['user']['role'])
) {

    $userRole =
        strtolower(
            trim(
                (string) $_SESSION['user']['role']
            )
        );
}


/* ============================================================
   GET FORM VALUES
   ============================================================ */

$researchId =
    isset($_POST['research_id'])
        ? (int) $_POST['research_id']
        : 0;


$toStatus =
    strtoupper(
        trim(
            $_POST['to_status'] ?? ''
        )
    );


$actionCode =
    strtoupper(
        trim(
            $_POST['action_code']
            ?? 'CHANGE_STATUS'
        )
    );


$remarks =
    trim(
        $_POST['remarks'] ?? ''
    );


/* ============================================================
   BASIC VALIDATION
   ============================================================ */

if ($researchId <= 0) {

    $_SESSION['error_message'] =
        'Invalid research record.';

    header('Location: ../dashboard.php');
    exit;
}


if ($toStatus === '') {

    $_SESSION['error_message'] =
        'The next research status is required.';

    header('Location: ../dashboard.php');
    exit;
}


if (!ResearchStatus::isValid($toStatus)) {

    $_SESSION['error_message'] =
        'Invalid research workflow status.';

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   GET RESEARCH RECORD
   ============================================================ */

try {

    $research =
        $researchService->getResearchById(
            $researchId
        );


    if (!$research) {

        $_SESSION['error_message'] =
            'Research record not found.';

        header('Location: ../dashboard.php');
        exit;
    }


} catch (Throwable $e) {

    error_log(
        'Change Status - Research Lookup Error: '
        . $e->getMessage()
    );

    $_SESSION['error_message'] =
        'Unable to retrieve the research record.';

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   ARCHIVED / FINISHED PROTECTION
   ============================================================ */

if (
    (int) $research['is_archived'] === 1
) {

    $_SESSION['error_message'] =
        'Archived research can no longer continue in the workflow.';

    header('Location: ../dashboard.php');
    exit;
}


if (
    ResearchStatus::isFinal(
        $research['current_status']
    )
) {

    $_SESSION['error_message'] =
        'This research has already reached a final status.';

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   IDENTIFY RESEARCHER ROLE
   ============================================================ */

$isResearcher = in_array(
    $userRole,
    [
        'researcher',
        'faculty',
        'faculty researcher'
    ],
    true
);


$isRps = in_array(
    $userRole,
    [
        'rps',
        'research personnel',
        'research staff'
    ],
    true
);


$isAdmin = in_array(
    $userRole,
    [
        'admin',
        'administrator'
    ],
    true
);


/* ============================================================
   RESEARCH OWNERSHIP CHECK
   ============================================================ */

/*
 * If the logged-in user is a researcher,
 * they must own the research record.
 */

if (
    $isResearcher &&
    (int) $research['researcher_id'] !== $userId
) {

    $_SESSION['error_message'] =
        'You are not allowed to modify this research record.';

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   ALLOWED RESEARCHER TRANSITIONS
   ============================================================ */

/*
 * Researchers should not be able to manually approve
 * their own research.
 *
 * They may only perform researcher-side actions.
 */

$researcherAllowedStatuses = [

    // Submit initial research
    ResearchStatus::SUBMITTED,

    // After ethics result, regular researcher clicks For URRC
    ResearchStatus::FOR_URRC,

    // Self-initiated path
    ResearchStatus::SELF_INITIATED_UPE_REQUIRED,

    // UPE certificates submitted
    ResearchStatus::SELF_INITIATED_UPE_SUBMITTED,

    // URRC revision uploaded
    ResearchStatus::URRC_REVISED_SUBMITTED,

    // In-House revision uploaded
    ResearchStatus::INHOUSE_REVISED_SUBMITTED,

    // Completed manuscript uploaded
    ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED,

    // Terminal report submitted
    ResearchStatus::TERMINAL_REPORT_SUBMITTED,

    // Final proof uploaded
    ResearchStatus::COMPLETED
];


/* ============================================================
   ALLOWED RPS / ADMIN TRANSITIONS
   ============================================================ */

/*
 * Review RESULT transitions are handled by separate files:
 *
 * save_ethics_result.php
 * save_urrc_result.php
 * save_inhouse_result.php
 *
 * This list is only for other RPS/Admin workflow actions.
 */

$rpsAllowedStatuses = [

    // Start UREC/IACUC review
    ResearchStatus::FOR_ETHICS_REVIEW,

    // Generate / Issue Notice to Proceed
    ResearchStatus::NTP_ISSUED,

    // Issue Permit
    ResearchStatus::PERMIT_ISSUED,

    // Archive because researcher did not revise
    ResearchStatus::ARCHIVED_NO_REVISION
];


/* ============================================================
   ROLE-BASED SECURITY
   ============================================================ */

/*
 * If role is recognized as researcher,
 * restrict to researcher actions.
 */

if ($isResearcher) {

    if (
        !in_array(
            $toStatus,
            $researcherAllowedStatuses,
            true
        )
    ) {

        $_SESSION['error_message'] =
            'You are not authorized to perform that research action.';

        header('Location: ../dashboard.php');
        exit;
    }
}


/*
 * If role is RPS, restrict to RPS actions.
 */

if ($isRps) {

    if (
        !in_array(
            $toStatus,
            $rpsAllowedStatuses,
            true
        )
    ) {

        $_SESSION['error_message'] =
            'You are not authorized to perform that workflow action.';

        header('Location: ../dashboard.php');
        exit;
    }
}


/*
 * Admin is allowed to perform RPS-type workflow actions.
 *
 * We still rely on ResearchWorkflow validation,
 * so Admin cannot skip workflow stages.
 */

if ($isAdmin) {

    $adminAllowedStatuses =
        $rpsAllowedStatuses;

    if (
        !in_array(
            $toStatus,
            $adminAllowedStatuses,
            true
        )
    ) {

        $_SESSION['error_message'] =
            'This action must be processed through its proper review module.';

        header('Location: ../dashboard.php');
        exit;
    }
}


/* ============================================================
   UNKNOWN ROLE PROTECTION
   ============================================================ */

/*
 * Kapag walang role na nakita sa session,
 * huwag muna payagan.
 *
 * Mas safe ito kaysa hayaan ang kahit sinong
 * logged-in user na mag-change ng status.
 */

if (
    !$isResearcher &&
    !$isRps &&
    !$isAdmin
) {

    $_SESSION['error_message'] =
        'Your account role is not authorized for this action.';

    error_log(
        'Unknown ASU-REMS role during workflow action: '
        . $userRole
    );

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   CHANGE STATUS
   ============================================================ */

try {

    $updatedResearch =
        $researchService->changeStatus(
            $researchId,
            $toStatus,
            $userId,
            $actionCode,
            $remarks
        );


    /* --------------------------------------------------------
       SUCCESS MESSAGE
       -------------------------------------------------------- */

    switch ($toStatus) {

        case ResearchStatus::SUBMITTED:

            $_SESSION['success_message'] =
                'Research submitted successfully. Manuscript number generated: '
                . $updatedResearch['manuscript_no'];

            break;


        case ResearchStatus::FOR_ETHICS_REVIEW:

            $_SESSION['success_message'] =
                'Research has been forwarded for UREC/IACUC review.';

            break;


        case ResearchStatus::FOR_URRC:

            $_SESSION['success_message'] =
                'Research has been submitted for URRC review.';

            break;


        case ResearchStatus::SELF_INITIATED_UPE_REQUIRED:

            $_SESSION['success_message'] =
                'Research has been marked as Self-Initiated. Please submit the required UPE certificates.';

            break;


        case ResearchStatus::SELF_INITIATED_UPE_SUBMITTED:

            $_SESSION['success_message'] =
                'UPE certificate requirements have been submitted.';

            break;


        case ResearchStatus::URRC_REVISED_SUBMITTED:

            $_SESSION['success_message'] =
                'Revised manuscript submitted successfully for URRC.';

            break;


        case ResearchStatus::INHOUSE_REVISED_SUBMITTED:

            $_SESSION['success_message'] =
                'Revised manuscript submitted successfully for In-House review.';

            break;


        case ResearchStatus::NTP_ISSUED:

            $_SESSION['success_message'] =
                'Notice to Proceed has been issued successfully.';

            break;


        case ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED:

            $_SESSION['success_message'] =
                'Completed research manuscript submitted successfully.';

            break;


        case ResearchStatus::TERMINAL_REPORT_SUBMITTED:

            $_SESSION['success_message'] =
                'Terminal report submitted successfully.';

            break;


        case ResearchStatus::PERMIT_ISSUED:

            $_SESSION['success_message'] =
                'Permit to publish/present has been issued successfully.';

            break;


        case ResearchStatus::COMPLETED:

            $_SESSION['success_message'] =
                'Research workflow completed successfully.';

            break;


        case ResearchStatus::ARCHIVED_NO_REVISION:

            $_SESSION['success_message'] =
                'Research has been archived because the required revision was not submitted.';

            break;


        default:

            $_SESSION['success_message'] =
                'Research status updated successfully.';
    }


    /* --------------------------------------------------------
       REDIRECT
       -------------------------------------------------------- */

    header(
        'Location: ../dashboard.php'
        . '?research_id='
        . urlencode(
            (string) $researchId
        )
    );

    exit;


/* ============================================================
   ERROR HANDLING
   ============================================================ */

} catch (Throwable $e) {

    error_log(
        'Research Status Change Error: '
        . $e->getMessage()
    );


    /*
     * Development-friendly message.
     *
     * We do not display the raw SQL error,
     * but workflow errors are useful to the user.
     */

    $safeWorkflowMessages = [

        'Invalid research workflow transition',

        'Regular research must proceed to URRC review.',

        'Self-initiated research must proceed to UPE certificate submission.',

        'Self-initiated research cannot enter the regular URRC workflow.'
    ];


    $message =
        $e->getMessage();


    $canShowMessage =
        false;


    foreach (
        $safeWorkflowMessages
        as $safeMessage
    ) {

        if (
            str_contains(
                $message,
                $safeMessage
            )
        ) {

            $canShowMessage = true;
            break;
        }
    }


    if ($canShowMessage) {

        $_SESSION['error_message'] =
            $message;

    } else {

        $_SESSION['error_message'] =
            'Unable to update the research status. Please try again.';
    }


    header(
        'Location: ../dashboard.php'
        . '?research_id='
        . urlencode(
            (string) $researchId
        )
    );

    exit;
}