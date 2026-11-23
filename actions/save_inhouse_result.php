<?php

/**
 * ============================================================
 * ASU-REMS
 * SAVE IN-HOUSE REVIEW RESULT
 * ============================================================
 *
 * Possible results:
 *
 * PASSED
 * PASSED_WITH_REVISION
 * FAILED
 *
 * Allowed current statuses:
 *
 * FOR_INHOUSE
 * INHOUSE_REVISED_SUBMITTED
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
   POST REQUEST ONLY
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   CSRF SECURITY
   ============================================================ */

require_csrf();


/* ============================================================
   GET LOGGED-IN USER ID
   ============================================================ */

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
   LOGIN CHECK
   ============================================================ */

if ($userId <= 0) {

    $_SESSION['error_message'] =
        'Please log in before processing an In-House review.';

    header('Location: ../login.php');
    exit;
}


/* ============================================================
   GET USER ROLE
   ============================================================ */

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
   AUTHORIZED ROLES
   ============================================================ */

/*
 * For now:
 *
 * RPS
 * Research Personnel
 * Research Staff
 * Admin
 * Administrator
 *
 * can encode In-House results.
 *
 * Later we can adjust this to match the exact
 * role names in your existing users table/login.
 */

$authorizedRoles = [

    'rps',
    'research personnel',
    'research staff',

    'admin',
    'administrator'
];


if (
    !in_array(
        $userRole,
        $authorizedRoles,
        true
    )
) {

    $_SESSION['error_message'] =
        'You are not authorized to encode an In-House review result.';

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   GET FORM DATA
   ============================================================ */

$researchId =
    isset($_POST['research_id'])
        ? (int) $_POST['research_id']
        : 0;


$result =
    strtoupper(
        trim(
            $_POST['result'] ?? ''
        )
    );


$remarks =
    trim(
        $_POST['remarks'] ?? ''
    );


/* ============================================================
   VALIDATE RESEARCH ID
   ============================================================ */

if ($researchId <= 0) {

    $_SESSION['error_message'] =
        'Invalid research record.';

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   VALIDATE RESULT
   ============================================================ */

$allowedResults = [

    'PASSED',

    'PASSED_WITH_REVISION',

    'FAILED'
];


if (
    !in_array(
        $result,
        $allowedResults,
        true
    )
) {

    $_SESSION['error_message'] =
        'Please select a valid In-House review result.';

    header(
        'Location: ../dashboard.php'
        . '?research_id='
        . urlencode(
            (string) $researchId
        )
    );

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


    /* --------------------------------------------------------
       ARCHIVED PROTECTION
       -------------------------------------------------------- */

    if (
        (int) $research['is_archived'] === 1
    ) {

        $_SESSION['error_message'] =
            'Archived research can no longer be reviewed.';

        header(
            'Location: ../dashboard.php'
            . '?research_id='
            . urlencode(
                (string) $researchId
            )
        );

        exit;
    }


    /* --------------------------------------------------------
       ALLOWED CURRENT STATUS
       -------------------------------------------------------- */

    /*
     * Initial In-House review:
     *
     * FOR_INHOUSE
     *
     * Repeat review after revision:
     *
     * INHOUSE_REVISED_SUBMITTED
     */

    $allowedStatuses = [

        ResearchStatus::FOR_INHOUSE,

        ResearchStatus::INHOUSE_REVISED_SUBMITTED
    ];


    if (
        !in_array(
            $research['current_status'],
            $allowedStatuses,
            true
        )
    ) {

        $_SESSION['error_message'] =
            'This research is not currently ready for In-House review.';

        header(
            'Location: ../dashboard.php'
            . '?research_id='
            . urlencode(
                (string) $researchId
            )
        );

        exit;
    }


} catch (Throwable $e) {

    error_log(
        'In-House Research Lookup Error: '
        . $e->getMessage()
    );


    $_SESSION['error_message'] =
        'Unable to retrieve the research record.';

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   SAVE IN-HOUSE RESULT
   ============================================================ */

try {

    $updatedResearch =
        $researchService->saveInhouseResult(
            $researchId,
            $result,
            $remarks,
            $userId
        );


    /* ========================================================
       SUCCESS MESSAGE
       ======================================================== */

    switch ($result) {

        /* ----------------------------------------------------
           PASSED
           ---------------------------------------------------- */

        case 'PASSED':

            $_SESSION['success_message'] =
                'In-House result saved successfully. '
                . 'The research is now ready for Notice to Proceed.';

            break;


        /* ----------------------------------------------------
           PASSED WITH REVISION
           ---------------------------------------------------- */

        case 'PASSED_WITH_REVISION':

            $_SESSION['success_message'] =
                'In-House result saved successfully. '
                . 'The researcher is required to submit a revised manuscript.';

            break;


        /* ----------------------------------------------------
           FAILED
           ---------------------------------------------------- */

        case 'FAILED':

            $_SESSION['success_message'] =
                'In-House result saved. '
                . 'The research failed the In-House review and has been archived.';

            break;


        default:

            $_SESSION['success_message'] =
                'In-House review result saved successfully.';
    }


    /* ========================================================
       REDIRECT
       ======================================================== */

    header(
        'Location: ../dashboard.php'
        . '?research_id='
        . urlencode(
            (string) $researchId
        )
        . '&inhouse_saved=1'
    );

    exit;


/* ============================================================
   ERROR HANDLING
   ============================================================ */

} catch (Throwable $e) {

    error_log(
        'Save In-House Result Error: '
        . $e->getMessage()
    );


    $errorMessage =
        $e->getMessage();


    /*
     * Safe workflow messages that may
     * be shown directly to the user.
     */

    if (
        str_contains(
            $errorMessage,
            'not currently ready for In-House review'
        )
        ||
        str_contains(
            $errorMessage,
            'Invalid research workflow transition'
        )
        ||
        str_contains(
            $errorMessage,
            'Archived research'
        )
    ) {

        $_SESSION['error_message'] =
            $errorMessage;

    } else {

        $_SESSION['error_message'] =
            'Unable to save the In-House review result. Please try again.';
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