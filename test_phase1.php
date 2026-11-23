<?php

/**
 * ============================================================
 * ASU-REMS
 * PHASE 1 SYSTEM TEST
 * ============================================================
 *
 * Safe test only.
 *
 * This file DOES NOT:
 * - create research
 * - change statuses in database
 * - delete records
 *
 * It only checks:
 * - database connection
 * - required tables
 * - required columns
 * - PHP classes
 * - workflow rules
 * - ResearchService loading
 * - action files
 */


/* ============================================================
   REQUIRED FILES
   ============================================================ */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/research_statuses.php';
require_once __DIR__ . '/includes/research_workflow.php';
require_once __DIR__ . '/includes/research_helpers.php';
require_once __DIR__ . '/services/ResearchService.php';


/* ============================================================
   TEST STORAGE
   ============================================================ */

$tests = [];


/**
 * Add test result.
 */
function addTest(
    array &$tests,
    string $name,
    bool $passed,
    string $details = ''
): void {

    $tests[] = [
        'name' => $name,
        'passed' => $passed,
        'details' => $details
    ];
}


/* ============================================================
   TEST 1
   PHP VERSION
   ============================================================ */

$phpOkay =
    version_compare(
        PHP_VERSION,
        '8.0.0',
        '>='
    );

addTest(
    $tests,
    'PHP 8.0 or newer',
    $phpOkay,
    'Current PHP version: ' . PHP_VERSION
);


/* ============================================================
   TEST 2
   DATABASE CONNECTION
   ============================================================ */

try {

    $pdo->query('SELECT 1');

    addTest(
        $tests,
        'Database connection',
        true,
        'Connected successfully using PDO.'
    );

} catch (Throwable $e) {

    addTest(
        $tests,
        'Database connection',
        false,
        $e->getMessage()
    );
}


/* ============================================================
   TEST 3
   REQUIRED TABLES
   ============================================================ */

$requiredTables = [

    'research_projects',

    'manuscript_sequences',

    'research_status_history',

    'research_reviews',

    'research_documents'
];


foreach ($requiredTables as $table) {

    try {

        $stmt = $pdo->prepare(
            "
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE
                table_schema = DATABASE()
                AND table_name = :table_name
            "
        );


        $stmt->execute([
            ':table_name' => $table
        ]);


        $exists =
            (int) $stmt->fetchColumn() > 0;


        addTest(
            $tests,
            'Table: ' . $table,
            $exists,
            $exists
                ? 'Table exists.'
                : 'Table is missing.'
        );


    } catch (Throwable $e) {

        addTest(
            $tests,
            'Table: ' . $table,
            false,
            $e->getMessage()
        );
    }
}


/* ============================================================
   TEST 4
   RESEARCH PROJECT COLUMNS
   ============================================================ */

$requiredResearchColumns = [

    'id',

    'manuscript_no',

    'researcher_id',

    'title',

    'funding_source',

    'submission_track',

    'current_status',

    'current_stage',

    'is_archived',

    'archive_reason',

    'archived_at',

    'submitted_at',

    'completed_at',

    'created_at',

    'updated_at'
];


foreach (
    $requiredResearchColumns
    as $column
) {

    try {

        $stmt = $pdo->prepare(
            "
            SELECT COUNT(*)

            FROM information_schema.columns

            WHERE
                table_schema = DATABASE()

                AND table_name =
                    'research_projects'

                AND column_name =
                    :column_name
            "
        );


        $stmt->execute([
            ':column_name' =>
                $column
        ]);


        $exists =
            (int) $stmt->fetchColumn() > 0;


        addTest(
            $tests,
            'research_projects.' . $column,
            $exists,
            $exists
                ? 'Column exists.'
                : 'Column is missing.'
        );


    } catch (Throwable $e) {

        addTest(
            $tests,
            'research_projects.' . $column,
            false,
            $e->getMessage()
        );
    }
}


/* ============================================================
   TEST 5
   REVIEW TABLE COLUMNS
   ============================================================ */

$requiredReviewColumns = [

    'id',

    'research_id',

    'review_type',

    'review_round',

    'result',

    'remarks',

    'reviewed_by',

    'reviewed_at',

    'created_at'
];


foreach (
    $requiredReviewColumns
    as $column
) {

    try {

        $stmt = $pdo->prepare(
            "
            SELECT COUNT(*)

            FROM information_schema.columns

            WHERE
                table_schema = DATABASE()

                AND table_name =
                    'research_reviews'

                AND column_name =
                    :column_name
            "
        );


        $stmt->execute([
            ':column_name' =>
                $column
        ]);


        $exists =
            (int) $stmt->fetchColumn() > 0;


        addTest(
            $tests,
            'research_reviews.' . $column,
            $exists,
            $exists
                ? 'Column exists.'
                : 'Column is missing.'
        );


    } catch (Throwable $e) {

        addTest(
            $tests,
            'research_reviews.' . $column,
            false,
            $e->getMessage()
        );
    }
}


/* ============================================================
   TEST 6
   RESEARCH STATUS CLASS
   ============================================================ */

addTest(
    $tests,
    'ResearchStatus class loaded',
    class_exists('ResearchStatus'),
    class_exists('ResearchStatus')
        ? 'ResearchStatus is available.'
        : 'ResearchStatus could not be loaded.'
);


/* ============================================================
   TEST 7
   ALL STATUS VALUES
   ============================================================ */

$allStatuses =
    ResearchStatus::all();


addTest(
    $tests,
    'Research statuses available',
    count($allStatuses) >= 20,
    'Total statuses found: '
    . count($allStatuses)
);


/* ============================================================
   TEST 8
   STATUS VALIDATION
   ============================================================ */

addTest(
    $tests,
    'Valid status check',
    ResearchStatus::isValid(
        ResearchStatus::FOR_URRC
    ),
    'FOR_URRC is recognized.'
);


addTest(
    $tests,
    'Invalid status check',
    !ResearchStatus::isValid(
        'FAKE_STATUS'
    ),
    'FAKE_STATUS is correctly rejected.'
);


/* ============================================================
   TEST 9
   FINAL STATUS CHECK
   ============================================================ */

addTest(
    $tests,
    'Completed is final',
    ResearchStatus::isFinal(
        ResearchStatus::COMPLETED
    ),
    'COMPLETED correctly acts as a final status.'
);


addTest(
    $tests,
    'Draft is not final',
    !ResearchStatus::isFinal(
        ResearchStatus::DRAFT
    ),
    'DRAFT correctly remains active.'
);


/* ============================================================
   TEST 10
   WORKFLOW CLASS
   ============================================================ */

addTest(
    $tests,
    'ResearchWorkflow class loaded',
    class_exists(
        'ResearchWorkflow'
    ),
    class_exists(
        'ResearchWorkflow'
    )
        ? 'Workflow class is available.'
        : 'Workflow class could not be loaded.'
);


/* ============================================================
   TEST 11
   VALID WORKFLOW TRANSITIONS
   ============================================================ */

$validTransitions = [

    [
        ResearchStatus::DRAFT,
        ResearchStatus::SUBMITTED
    ],

    [
        ResearchStatus::SUBMITTED,
        ResearchStatus::FOR_ETHICS_REVIEW
    ],

    [
        ResearchStatus::FOR_ETHICS_REVIEW,
        ResearchStatus::ETHICS_COMPLETED
    ],

    [
        ResearchStatus::ETHICS_COMPLETED,
        ResearchStatus::FOR_URRC
    ],

    [
        ResearchStatus::FOR_URRC,
        ResearchStatus::URRC_REVISION_REQUIRED
    ],

    [
        ResearchStatus::URRC_REVISED_SUBMITTED,
        ResearchStatus::FOR_INHOUSE
    ],

    [
        ResearchStatus::FOR_INHOUSE,
        ResearchStatus::INHOUSE_REVISION_REQUIRED
    ],

    [
        ResearchStatus::INHOUSE_REVISED_SUBMITTED,
        ResearchStatus::READY_FOR_NTP
    ],

    [
        ResearchStatus::READY_FOR_NTP,
        ResearchStatus::NTP_ISSUED
    ],

    [
        ResearchStatus::NTP_ISSUED,
        ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED
    ],

    [
        ResearchStatus::TERMINAL_REPORT_SUBMITTED,
        ResearchStatus::PERMIT_ISSUED
    ],

    [
        ResearchStatus::PERMIT_ISSUED,
        ResearchStatus::COMPLETED
    ]
];


foreach (
    $validTransitions
    as $transition
) {

    [$from, $to] =
        $transition;


    $passed =
        ResearchWorkflow::canTransition(
            $from,
            $to
        );


    addTest(
        $tests,
        $from . ' → ' . $to,
        $passed,
        $passed
            ? 'Transition allowed correctly.'
            : 'This transition should be allowed.'
    );
}


/* ============================================================
   TEST 12
   REVISED URRC TRANSITIONS
   ============================================================ */

addTest(
    $tests,
    'URRC revised → revision again',
    ResearchWorkflow::canTransition(
        ResearchStatus::URRC_REVISED_SUBMITTED,
        ResearchStatus::URRC_REVISION_REQUIRED
    ),
    'Repeat URRC revision is supported.'
);


addTest(
    $tests,
    'URRC revised → rejected',
    ResearchWorkflow::canTransition(
        ResearchStatus::URRC_REVISED_SUBMITTED,
        ResearchStatus::URRC_REJECTED
    ),
    'URRC may reject after revised submission.'
);


/* ============================================================
   TEST 13
   REVISED IN-HOUSE TRANSITIONS
   ============================================================ */

addTest(
    $tests,
    'In-House revised → revision again',
    ResearchWorkflow::canTransition(
        ResearchStatus::INHOUSE_REVISED_SUBMITTED,
        ResearchStatus::INHOUSE_REVISION_REQUIRED
    ),
    'Repeat In-House revision is supported.'
);


addTest(
    $tests,
    'In-House revised → failed',
    ResearchWorkflow::canTransition(
        ResearchStatus::INHOUSE_REVISED_SUBMITTED,
        ResearchStatus::INHOUSE_FAILED
    ),
    'In-House may fail after revised submission.'
);


/* ============================================================
   TEST 14
   INVALID TRANSITIONS
   ============================================================ */

$invalidTransitions = [

    [
        ResearchStatus::DRAFT,
        ResearchStatus::COMPLETED
    ],

    [
        ResearchStatus::SUBMITTED,
        ResearchStatus::FOR_URRC
    ],

    [
        ResearchStatus::FOR_URRC,
        ResearchStatus::NTP_ISSUED
    ],

    [
        ResearchStatus::COMPLETED,
        ResearchStatus::DRAFT
    ]
];


foreach (
    $invalidTransitions
    as $transition
) {

    [$from, $to] =
        $transition;


    $blocked =
        !ResearchWorkflow::canTransition(
            $from,
            $to
        );


    addTest(
        $tests,
        'Block: '
        . $from
        . ' → '
        . $to,
        $blocked,
        $blocked
            ? 'Invalid transition correctly blocked.'
            : 'WARNING: Invalid transition was allowed.'
    );
}


/* ============================================================
   TEST 15
   SELF-INITIATED WORKFLOW
   ============================================================ */

addTest(
    $tests,
    'Ethics → UPE requirement',
    ResearchWorkflow::canTransition(
        ResearchStatus::ETHICS_COMPLETED,
        ResearchStatus::SELF_INITIATED_UPE_REQUIRED
    ),
    'Self-initiated branch is available.'
);


addTest(
    $tests,
    'UPE required → UPE submitted',
    ResearchWorkflow::canTransition(
        ResearchStatus::SELF_INITIATED_UPE_REQUIRED,
        ResearchStatus::SELF_INITIATED_UPE_SUBMITTED
    ),
    'UPE requirement can proceed to submitted.'
);


/* ============================================================
   TEST 16
   HELPERS
   ============================================================ */

$statusLabel =
    researchStatusLabel(
        ResearchStatus::FOR_URRC
    );


addTest(
    $tests,
    'Status label helper',
    $statusLabel === 'For URRC Review',
    'Result: ' . $statusLabel
);


$stageLabel =
    researchStageLabel(
        ResearchStatus::FOR_INHOUSE
    );


addTest(
    $tests,
    'Stage label helper',
    $stageLabel === 'In-House Review',
    'Result: ' . $stageLabel
);


/* ============================================================
   TEST 17
   RESEARCH SERVICE
   ============================================================ */

addTest(
    $tests,
    'ResearchService class loaded',
    class_exists(
        'ResearchService'
    ),
    class_exists(
        'ResearchService'
    )
        ? 'ResearchService is available.'
        : 'ResearchService could not be loaded.'
);


addTest(
    $tests,
    'ResearchService instance created',
    isset($researchService)
    &&
    $researchService
        instanceof ResearchService,
    isset($researchService)
        ? 'Service instance is ready.'
        : 'Service instance is missing.'
);


/* ============================================================
   TEST 18
   SAFE DATABASE READ
   ============================================================ */

try {

    $activeResearch =
        $researchService
            ->getActiveResearch();


    addTest(
        $tests,
        'Read active research',
        is_array($activeResearch),
        'Active records found: '
        . count($activeResearch)
    );


} catch (Throwable $e) {

    addTest(
        $tests,
        'Read active research',
        false,
        $e->getMessage()
    );
}


/* ============================================================
   TEST 19
   ACTION FILES
   ============================================================ */

$requiredActionFiles = [

    'create_research.php',

    'change_status.php',

    'save_ethics_result.php',

    'save_urrc_result.php',

    'save_inhouse_result.php'
];


foreach (
    $requiredActionFiles
    as $file
) {

    $path =
        __DIR__
        . '/actions/'
        . $file;


    $exists =
        file_exists($path);


    addTest(
        $tests,
        'Action: ' . $file,
        $exists,
        $exists
            ? 'File exists.'
            : 'File is missing.'
    );
}


/* ============================================================
   FINAL RESULTS
   ============================================================ */

$totalTests =
    count($tests);


$passedTests =
    count(
        array_filter(
            $tests,
            fn($test) =>
                $test['passed']
        )
    );


$failedTests =
    $totalTests - $passedTests;


$allPassed =
    $failedTests === 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        ASU-REMS Phase 1 Test
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            background: #f5f6f8;
            font-family:
                "Century Gothic",
                Arial,
                sans-serif;
            color: #222;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow:
                0 5px 18px
                rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
        }

        .summary {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 25px 0;
        }

        .summary-box {
            padding: 15px 20px;
            background: #f1f3f5;
            border-radius: 8px;
            min-width: 150px;
        }

        .success-summary {
            background: #e8f5e9;
        }

        .error-summary {
            background: #ffebee;
        }

        .test {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .passed {
            background: #f1f8e9;
            border-color: #a5d6a7;
        }

        .failed {
            background: #ffebee;
            border-color: #ef9a9a;
        }

        .test-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .details {
            font-size: 13px;
            color: #555;
            word-break: break-word;
        }

        .final {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
        }

        .final-success {
            background: #e8f5e9;
        }

        .final-error {
            background: #ffebee;
        }

        code {
            background: #eee;
            padding: 3px 6px;
            border-radius: 4px;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>
        ASU-REMS Phase 1 Test
    </h1>

    <p>
        Safe system check — no research records
        are created, changed, or deleted.
    </p>


    <div class="summary">

        <div class="summary-box">

            <strong>
                Total Tests
            </strong>

            <br>

            <?= $totalTests ?>

        </div>


        <div class="
            summary-box
            success-summary
        ">

            <strong>
                Passed
            </strong>

            <br>

            <?= $passedTests ?>

        </div>


        <div class="
            summary-box
            <?= $failedTests > 0
                ? 'error-summary'
                : 'success-summary' ?>
        ">

            <strong>
                Failed
            </strong>

            <br>

            <?= $failedTests ?>

        </div>

    </div>


    <?php foreach ($tests as $test): ?>

        <div class="
            test
            <?= $test['passed']
                ? 'passed'
                : 'failed' ?>
        ">

            <div class="test-title">

                <?= $test['passed']
                    ? '✅'
                    : '❌' ?>

                <?= htmlspecialchars(
                    $test['name']
                ) ?>

            </div>


            <?php if (
                $test['details'] !== ''
            ): ?>

                <div class="details">

                    <?= htmlspecialchars(
                        $test['details']
                    ) ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>


    <?php if ($allPassed): ?>

        <div class="
            final
            final-success
        ">

            <strong>
                ✅ Phase 1 backend foundation passed all tests.
            </strong>

            <p>
                Database, workflow rules,
                service files and action files
                are ready for integration.
            </p>

            <p>
                You can now proceed to the
                Research interface integration.
            </p>

        </div>

    <?php else: ?>

        <div class="
            final
            final-error
        ">

            <strong>
                ❌ Some Phase 1 tests failed.
            </strong>

            <p>
                Do not continue to the UI yet.
                Check the red test result above.
            </p>

        </div>

    <?php endif; ?>


    <p style="margin-top: 30px;">

        After testing, delete or rename:

        <code>
            test_phase1.php
        </code>

    </p>

</div>

</body>

</html>