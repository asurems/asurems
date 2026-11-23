<?php

/**
 * ASU-REMS
 * Create Research Action
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../services/ResearchService.php';


/* ============================================================
   ONLY ALLOW POST REQUEST
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   CSRF CHECK
   ============================================================ */

require_csrf();


/* ============================================================
   GET LOGGED-IN USER ID
   ============================================================ */

/*
 * Flexible muna ito para suportahan ang common session formats.
 *
 * Supported:
 *
 * $_SESSION['user_id']
 *
 * or
 *
 * $_SESSION['user']['id']
 */

$researcherId = 0;


if (!empty($_SESSION['user_id'])) {

    $researcherId =
        (int) $_SESSION['user_id'];

} elseif (
    !empty($_SESSION['user']) &&
    !empty($_SESSION['user']['id'])
) {

    $researcherId =
        (int) $_SESSION['user']['id'];
}


/* ============================================================
   MAKE SURE USER IS LOGGED IN
   ============================================================ */

if ($researcherId <= 0) {

    $_SESSION['error_message'] =
        'Please log in before creating a research record.';

    header('Location: ../login.php');
    exit;
}


/* ============================================================
   GET FORM DATA
   ============================================================ */

$title =
    trim($_POST['title'] ?? '');

$fundingSource =
    trim($_POST['funding_source'] ?? '');

$submissionTrack =
    strtoupper(
        trim(
            $_POST['submission_track']
            ?? 'REGULAR'
        )
    );


/* ============================================================
   BASIC VALIDATION
   ============================================================ */

$errors = [];


if ($title === '') {

    $errors[] =
        'Research title is required.';
}


if (strlen($title) > 255) {

    $errors[] =
        'Research title must not exceed 255 characters.';
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

    $errors[] =
        'Invalid research submission type.';
}


/* ============================================================
   IF VALIDATION FAILED
   ============================================================ */

if (!empty($errors)) {

    $_SESSION['error_message'] =
        implode(' ', $errors);

    $_SESSION['old_research_form'] = [
        'title' => $title,
        'funding_source' => $fundingSource,
        'submission_track' => $submissionTrack
    ];

    header('Location: ../dashboard.php');
    exit;
}


/* ============================================================
   CREATE RESEARCH
   ============================================================ */

try {

    $researchId =
        $researchService->createResearch(
            $researcherId,
            $title,
            $fundingSource,
            $submissionTrack
        );


    /* --------------------------------------------------------
       SUCCESS MESSAGE
       -------------------------------------------------------- */

    $_SESSION['success_message'] =
        'Research record created successfully.';


    /*
     * Clear old form data.
     */

    unset(
        $_SESSION['old_research_form']
    );


    /*
     * For now, redirect muna tayo sa dashboard
     * dahil hindi pa natin ginagawa ang
     * research/my_research.php.
     */

    header(
        'Location: ../dashboard.php'
        . '?research_created=1'
        . '&research_id='
        . urlencode((string) $researchId)
    );

    exit;


} catch (Throwable $e) {

    /*
     * Huwag nating ipakita ang raw database error
     * directly sa user.
     */

    $_SESSION['error_message'] =
        'Unable to create the research record. Please try again.';


    /*
     * Keep entered form values.
     */

    $_SESSION['old_research_form'] = [
        'title' => $title,
        'funding_source' => $fundingSource,
        'submission_track' => $submissionTrack
    ];


    /*
     * Optional:
     * During development, puwede ninyong i-log
     * ang actual error.
     */

    error_log(
        'Create Research Error: '
        . $e->getMessage()
    );


    header('Location: ../dashboard.php');
    exit;
}