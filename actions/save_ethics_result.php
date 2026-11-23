<?php

/**
 * ============================================================
 * ASU-REMS
 * SAVE UREC / IACUC RESULT
 * ============================================================
 *
 * Saves the ethics review result:
 *
 * EXEMPT
 * EXPEDITED
 * FULL_BOARD
 *
 * After saving:
 *
 * FOR_ETHICS_REVIEW
 *        ↓
 * ETHICS_COMPLETED
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
require_once __DIR__ . '/../services/ResearchService.php';


/* ============================================================
   ONLY POST REQUEST
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   CSRF PROTECTION
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
   USER MUST BE LOGGED IN
   ============================================================ */

if ($userId <= 0) {

    $_SESSION['error_message'] =
        'Please log in before processing an ethics review.';

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
 * For now, these are the accepted RPS/Admin
 * role names.
 *
 * Later we will adjust this based on the
 * exact roles used by your login system.
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
        'You are not authorized to encode a UREC/IACUC result.';

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
   VALIDATION
   ============================================================ */

if ($researchId <= 0) {

    $_SESSION['error_message'] =
        'Invalid research record.';

    header('Location: ../dashboard.php');
    exit;
}


$allowedResults = [

    'EXEMPT',
    'EXPEDITED',
    'FULL_BOARD'
];


if (
    !in_array(
        $result,
        $allowedResults,
        true
    )
) {

    $_SESSION['error_message'] =
        'Please select a valid UREC/IACUC result.';

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
   CHECK RESEARCH RECORD
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


    /*
     * Only research currently under ethics review
     * may receive a UREC/IACUC result.
     */

    if (
        $research['current_status']
        !== ResearchStatus::FOR_ETHICS_REVIEW
    ) {

        $_SESSION['error_message'] =
            'This research is not currently for UREC/IACUC review.';

        header(
            'Location: ../dashboard.php'
            . '?research_id='
            . urlencode(
                (string) $researchId
            )
        );

        exit;
    }


    /*
     * Archived research cannot continue.
     */

    if (
        (int) $research['is_archived'] === 1
    ) {

        $_SESSION['error_message'] =
            'Archived research can no longer be reviewed.';

        header('Location: ../dashboard.php');
        exit;
    }


} catch (Throwable $e) {

    error_log(
        'Ethics Research Lookup Error: '
        . $e->getMessage()
    );


    $_SESSION['error_message'] =
        'Unable to retrieve the research record.';

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   SAVE ETHICS RESULT
   ============================================================ */

try {

    $updatedResearch =
        $researchService->saveEthicsResult(
            $researchId,
            $result,
            $remarks,
            $userId
        );


    /* ========================================================
       SUCCESS MESSAGE
       ======================================================== */

    switch ($result) {

        case 'EXEMPT':

            $resultLabel =
                'Exempt';

            break;


        case 'EXPEDITED':

            $resultLabel =
                'Expedited';

            break;


        case 'FULL_BOARD':

            $resultLabel =
                'Full-board';

            break;


        default:

            $resultLabel =
                $result;
    }


    $_SESSION['success_message'] =
        'UREC/IACUC result saved successfully: '
        . $resultLabel
        . '.';


    /*
     * After successful result:
     *
     * current_status =
     * ETHICS_COMPLETED
     */

    header(
        'Location: ../dashboard.php'
        . '?research_id='
        . urlencode(
            (string) $researchId
        )
        . '&ethics_saved=1'
    );

    exit;


/* ============================================================
   ERROR HANDLING
   ============================================================ */

} catch (Throwable $e) {

    error_log(
        'Save Ethics Result Error: '
        . $e->getMessage()
    );


    /*
     * Allow safe workflow error message.
     */

    if (
        str_contains(
            $e->getMessage(),
            'not currently for UREC/IACUC review'
        )
    ) {

        $_SESSION['error_message'] =
            $e->getMessage();

    } else {

        $_SESSION['error_message'] =
            'Unable to save the UREC/IACUC result. Please try again.';
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