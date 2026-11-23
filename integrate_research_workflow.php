<?php

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$user = currentUser();


/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if (
    ($user['role'] ?? '') !== 'admin'
) {

    http_response_code(403);

    exit(
        'Administrator access is required to run the Research Workflow integration.'
    );
}


/*
|--------------------------------------------------------------------------
| OUTPUT
|--------------------------------------------------------------------------
*/

$results = [];

$success = true;


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function integrationTableExists(
    PDO $pdo,
    string $table
): bool {

    $stmt =
        $pdo->prepare("
            SELECT COUNT(*)

            FROM information_schema.TABLES

            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");

    $stmt->execute([
        $table
    ]);

    return
        (int)$stmt->fetchColumn()
        > 0;
}


function integrationColumnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {

    $stmt =
        $pdo->prepare("
            SELECT COUNT(*)

            FROM information_schema.COLUMNS

            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");

    $stmt->execute([
        $table,
        $column
    ]);

    return
        (int)$stmt->fetchColumn()
        > 0;
}


function integrationIndexExists(
    PDO $pdo,
    string $table,
    string $index
): bool {

    $stmt =
        $pdo->prepare("
            SELECT COUNT(*)

            FROM information_schema.STATISTICS

            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ");

    $stmt->execute([
        $table,
        $index
    ]);

    return
        (int)$stmt->fetchColumn()
        > 0;
}


function integrationAddResult(
    array &$results,
    string $type,
    string $message
): void {

    $results[] = [
        'type' => $type,
        'message' => $message
    ];
}


/*
|--------------------------------------------------------------------------
| REQUIRED TABLE CHECK
|--------------------------------------------------------------------------
*/

if (
    !integrationTableExists(
        $pdo,
        'research_proposals'
    )
) {

    http_response_code(500);

    exit(
        'research_proposals table was not found. Integration stopped.'
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | BACKUP EXISTING RESEARCH PROPOSALS
    |--------------------------------------------------------------------------
    |
    | This creates a one-time snapshot before adding workflow fields.
    |
    */

    if (
        !integrationTableExists(
            $pdo,
            'research_proposals_workflow_backup'
        )
    ) {

        $pdo->exec("
            CREATE TABLE
                research_proposals_workflow_backup
            LIKE
                research_proposals
        ");


        $pdo->exec("
            INSERT INTO
                research_proposals_workflow_backup
            SELECT *
            FROM
                research_proposals
        ");


        integrationAddResult(
            $results,
            'success',
            'Backup of research_proposals created successfully.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'research_proposals backup already exists. No duplicate backup was created.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | BACKUP STATUS HISTORY
    |--------------------------------------------------------------------------
    */

    if (
        integrationTableExists(
            $pdo,
            'research_status_history'
        )
        &&
        !integrationTableExists(
            $pdo,
            'research_status_history_workflow_backup'
        )
    ) {

        $pdo->exec("
            CREATE TABLE
                research_status_history_workflow_backup
            LIKE
                research_status_history
        ");


        $pdo->exec("
            INSERT INTO
                research_status_history_workflow_backup
            SELECT *
            FROM
                research_status_history
        ");


        integrationAddResult(
            $results,
            'success',
            'Backup of research_status_history created successfully.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'Status history backup already exists or source table was unavailable.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MANUSCRIPT NUMBER
    |--------------------------------------------------------------------------
    */

    if (
        !integrationColumnExists(
            $pdo,
            'research_proposals',
            'manuscript_no'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD COLUMN manuscript_no
                VARCHAR(40)
                NULL
                AFTER research_id
        ");


        integrationAddResult(
            $results,
            'success',
            'Added manuscript_no.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'manuscript_no already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUBMISSION TRACK
    |--------------------------------------------------------------------------
    |
    | REGULAR
    | SELF_INITIATED
    |
    */

    if (
        !integrationColumnExists(
            $pdo,
            'research_proposals',
            'submission_track'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD COLUMN submission_track
                VARCHAR(30)
                NOT NULL
                DEFAULT 'REGULAR'
                AFTER funding_source
        ");


        integrationAddResult(
            $results,
            'success',
            'Added submission_track.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'submission_track already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | WORKFLOW STATUS
    |--------------------------------------------------------------------------
    |
    | We DO NOT replace the existing `status` column.
    |
    | Existing status continues to support the current Faculty/Reports pages.
    | workflow_status drives the new RPS workflow.
    |
    */

    if (
        !integrationColumnExists(
            $pdo,
            'research_proposals',
            'workflow_status'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD COLUMN workflow_status
                VARCHAR(64)
                NOT NULL
                DEFAULT 'DRAFT'
                AFTER status
        ");


        integrationAddResult(
            $results,
            'success',
            'Added workflow_status.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'workflow_status already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | WORKFLOW STAGE
    |--------------------------------------------------------------------------
    */

    if (
        !integrationColumnExists(
            $pdo,
            'research_proposals',
            'workflow_stage'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD COLUMN workflow_stage
                VARCHAR(64)
                NOT NULL
                DEFAULT 'SUBMISSION'
                AFTER workflow_status
        ");


        integrationAddResult(
            $results,
            'success',
            'Added workflow_stage.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'workflow_stage already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ARCHIVE FLAG
    |--------------------------------------------------------------------------
    */

    if (
        !integrationColumnExists(
            $pdo,
            'research_proposals',
            'is_archived'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD COLUMN is_archived
                TINYINT(1)
                NOT NULL
                DEFAULT 0
                AFTER workflow_stage
        ");


        integrationAddResult(
            $results,
            'success',
            'Added is_archived.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'is_archived already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ARCHIVE REASON
    |--------------------------------------------------------------------------
    */

    if (
        !integrationColumnExists(
            $pdo,
            'research_proposals',
            'archived_reason'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD COLUMN archived_reason
                VARCHAR(255)
                NULL
                AFTER is_archived
        ");


        integrationAddResult(
            $results,
            'success',
            'Added archived_reason.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'archived_reason already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ARCHIVED DATE
    |--------------------------------------------------------------------------
    */

    if (
        !integrationColumnExists(
            $pdo,
            'research_proposals',
            'archived_at'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD COLUMN archived_at
                DATETIME
                NULL
                AFTER archived_reason
        ");


        integrationAddResult(
            $results,
            'success',
            'Added archived_at.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'archived_at already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | WORKFLOW UPDATED DATE
    |--------------------------------------------------------------------------
    */

    if (
        !integrationColumnExists(
            $pdo,
            'research_proposals',
            'workflow_updated_at'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD COLUMN workflow_updated_at
                DATETIME
                NULL
                AFTER archived_at
        ");


        integrationAddResult(
            $results,
            'success',
            'Added workflow_updated_at.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'workflow_updated_at already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UNIQUE MANUSCRIPT NUMBER
    |--------------------------------------------------------------------------
    */

    if (
        !integrationIndexExists(
            $pdo,
            'research_proposals',
            'uq_research_manuscript_no'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD UNIQUE INDEX
                uq_research_manuscript_no
                (manuscript_no)
        ");


        integrationAddResult(
            $results,
            'success',
            'Added unique manuscript number index.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'Manuscript number index already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | WORKFLOW STATUS INDEX
    |--------------------------------------------------------------------------
    */

    if (
        !integrationIndexExists(
            $pdo,
            'research_proposals',
            'idx_research_workflow_status'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD INDEX
                idx_research_workflow_status
                (workflow_status)
        ");


        integrationAddResult(
            $results,
            'success',
            'Added workflow status index.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | WORKFLOW STAGE INDEX
    |--------------------------------------------------------------------------
    */

    if (
        !integrationIndexExists(
            $pdo,
            'research_proposals',
            'idx_research_workflow_stage'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD INDEX
                idx_research_workflow_stage
                (workflow_stage)
        ");


        integrationAddResult(
            $results,
            'success',
            'Added workflow stage index.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ARCHIVE INDEX
    |--------------------------------------------------------------------------
    */

    if (
        !integrationIndexExists(
            $pdo,
            'research_proposals',
            'idx_research_is_archived'
        )
    ) {

        $pdo->exec("
            ALTER TABLE research_proposals

            ADD INDEX
                idx_research_is_archived
                (is_archived)
        ");


        integrationAddResult(
            $results,
            'success',
            'Added archive index.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MANUSCRIPT SEQUENCE TABLE
    |--------------------------------------------------------------------------
    */

    if (
        !integrationTableExists(
            $pdo,
            'manuscript_sequences'
        )
    ) {

        $pdo->exec("
            CREATE TABLE manuscript_sequences
            (
                sequence_year
                    INT
                    NOT NULL,

                last_number
                    INT
                    NOT NULL
                    DEFAULT 0,

                updated_at
                    TIMESTAMP
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY
                    (sequence_year)
            )
            ENGINE = InnoDB
            DEFAULT CHARSET = utf8mb4
            COLLATE = utf8mb4_unicode_ci
        ");


        integrationAddResult(
            $results,
            'success',
            'Created manuscript_sequences.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'manuscript_sequences already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | NEW WORKFLOW REVIEW TABLE
    |--------------------------------------------------------------------------
    |
    | We intentionally DO NOT modify research_status_history.
    |
    | It already contains real historical records.
    |
    */

    if (
        !integrationTableExists(
            $pdo,
            'research_workflow_reviews'
        )
    ) {

        $pdo->exec("
            CREATE TABLE research_workflow_reviews
            (
                review_id
                    BIGINT UNSIGNED
                    NOT NULL
                    AUTO_INCREMENT,

                research_id
                    INT UNSIGNED
                    NOT NULL,

                review_type
                    VARCHAR(30)
                    NOT NULL,

                review_round
                    INT UNSIGNED
                    NOT NULL
                    DEFAULT 1,

                result
                    VARCHAR(64)
                    NOT NULL,

                remarks
                    TEXT
                    NULL,

                reviewed_by
                    INT UNSIGNED
                    NULL,

                reviewed_at
                    DATETIME
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                created_at
                    TIMESTAMP
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY
                    (review_id),

                INDEX idx_workflow_review_research
                    (research_id),

                INDEX idx_workflow_review_type
                    (review_type),

                INDEX idx_workflow_review_result
                    (result),

                INDEX idx_workflow_review_reviewer
                    (reviewed_by),

                CONSTRAINT
                    fk_workflow_review_research

                    FOREIGN KEY
                    (research_id)

                    REFERENCES
                    research_proposals
                    (research_id)

                    ON DELETE CASCADE,

                CONSTRAINT
                    fk_workflow_review_user

                    FOREIGN KEY
                    (reviewed_by)

                    REFERENCES
                    users
                    (user_id)

                    ON DELETE SET NULL
            )
            ENGINE = InnoDB
            DEFAULT CHARSET = utf8mb4
            COLLATE = utf8mb4_unicode_ci
        ");


        integrationAddResult(
            $results,
            'success',
            'Created research_workflow_reviews.'
        );

    } else {

        integrationAddResult(
            $results,
            'info',
            'research_workflow_reviews already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MAP EXISTING LEGACY RECORDS
    |--------------------------------------------------------------------------
    |
    | We preserve the original `status`.
    |
    | Only workflow fields are initialized.
    |
    | Clear mappings:
    |
    | Draft      → DRAFT
    | Submitted  → SUBMITTED
    | Under Review → FOR_ETHICS_REVIEW
    | Completed  → COMPLETED
    | Archived   → ARCHIVED_NO_REVISION
    |
    | Other legacy statuses are intentionally marked LEGACY_RECORD
    | rather than guessing where they belong in the new workflow.
    |
    */

    $pdo->exec("
        UPDATE research_proposals

        SET

            workflow_status =
                CASE

                    WHEN status = 'Draft'
                        THEN 'DRAFT'

                    WHEN status = 'Submitted'
                        THEN 'SUBMITTED'

                    WHEN status = 'Under Review'
                        THEN 'FOR_ETHICS_REVIEW'

                    WHEN status = 'Completed'
                        THEN 'COMPLETED'

                    WHEN status = 'Archived'
                        THEN 'ARCHIVED_NO_REVISION'

                    WHEN status = 'Rejected'
                        THEN 'LEGACY_REJECTED'

                    WHEN status = 'For Revision'
                        THEN 'LEGACY_REVISION'

                    WHEN status = 'Approved'
                        THEN 'LEGACY_APPROVED'

                    WHEN status = 'Ongoing'
                        THEN 'LEGACY_ONGOING'

                    ELSE 'LEGACY_RECORD'

                END,

            workflow_stage =
                CASE

                    WHEN status IN
                    (
                        'Draft',
                        'Submitted'
                    )
                        THEN 'SUBMISSION'

                    WHEN status = 'Under Review'
                        THEN 'ETHICS'

                    WHEN status = 'Completed'
                        THEN 'COMPLETED'

                    WHEN status = 'Archived'
                        THEN 'ARCHIVED'

                    ELSE 'LEGACY'

                END,

            is_archived =
                CASE

                    WHEN status = 'Archived'
                        THEN 1

                    ELSE 0

                END,

            archived_at =
                CASE

                    WHEN status = 'Archived'
                        THEN COALESCE(
                            archived_at,
                            updated_at
                        )

                    ELSE archived_at

                END,

            workflow_updated_at =
                COALESCE(
                    workflow_updated_at,
                    updated_at,
                    created_at,
                    NOW()
                )
    ");


    integrationAddResult(
        $results,
        'success',
        'Existing research records were mapped to initial workflow states without changing their original status.'
    );


    /*
    |--------------------------------------------------------------------------
    | MANUSCRIPT NUMBER BACKFILL
    |--------------------------------------------------------------------------
    |
    | Only records that are no longer Draft receive a manuscript number.
    |
    */

    $records =
        $pdo->query("
            SELECT
                research_id,
                submitted_at,
                created_at

            FROM research_proposals

            WHERE status <> 'Draft'

              AND manuscript_no IS NULL

            ORDER BY
                COALESCE(
                    submitted_at,
                    created_at
                ) ASC,
                research_id ASC
        ")
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach (
        $records
        as
        $record
    ) {

        $recordDate =
            $record['submitted_at']
            ??
            $record['created_at']
            ??
            date('Y-m-d H:i:s');


        $sequenceYear =
            (int)date(
                'Y',
                strtotime(
                    $recordDate
                )
            );


        /*
        |--------------------------------------------------------------------------
        | INITIALIZE YEAR
        |--------------------------------------------------------------------------
        */

        $sequenceStmt =
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
                    sequence_year =
                        VALUES(sequence_year)
            ");


        $sequenceStmt->execute([
            $sequenceYear
        ]);


        /*
        |--------------------------------------------------------------------------
        | NEXT NUMBER
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        try {

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
                (int)$record['research_id']
            ]);


            $pdo->commit();


        } catch (Throwable $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            throw $e;
        }
    }


    integrationAddResult(
        $results,
        'success',
        'Manuscript numbers were assigned to applicable existing submitted records.'
    );


    /*
    |--------------------------------------------------------------------------
    | FINAL COUNTS
    |--------------------------------------------------------------------------
    */

    $proposalCount =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM research_proposals
            ")
            ->fetchColumn();


    $workflowCount =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM research_proposals
                WHERE workflow_status IS NOT NULL
            ")
            ->fetchColumn();


    $reviewCount =
        (int)$pdo
            ->query("
                SELECT COUNT(*)
                FROM research_workflow_reviews
            ")
            ->fetchColumn();


    integrationAddResult(
        $results,
        'success',
        'Integration completed. '
        .
        $workflowCount
        .
        ' of '
        .
        $proposalCount
        .
        ' research records now have workflow states.'
    );


} catch (Throwable $e) {

    $success = false;


    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    integrationAddResult(
        $results,
        'danger',
        'Integration stopped: '
        .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| LOAD FINAL DATA FOR DISPLAY
|--------------------------------------------------------------------------
*/

$workflowRows = [];


try {

    $workflowRows =
        $pdo->query("
            SELECT
                research_id,
                manuscript_no,
                research_title,
                status,
                workflow_status,
                workflow_stage,
                submission_track,
                is_archived

            FROM research_proposals

            ORDER BY research_id ASC
        ")
        ->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $e) {

    $workflowRows = [];
}

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Research Workflow Integration
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            padding: 35px;

            background:
                #f4f7f4;

            color:
                #273028;

            font-family:
                "Century Gothic",
                Arial,
                sans-serif;
        }


        .container {

            max-width:
                1200px;

            margin:
                0 auto;
        }


        h1 {

            margin:
                0 0 6px;
        }


        .subtitle {

            margin:
                0 0 26px;

            color:
                #737c75;
        }


        .result {

            margin-bottom:
                10px;

            padding:
                12px 15px;

            border-radius:
                8px;

            font-size:
                13px;
        }


        .success {

            background:
                #e7f6eb;

            color:
                #08752c;
        }


        .info {

            background:
                #eaf2fa;

            color:
                #315f86;
        }


        .danger {

            background:
                #fdeaea;

            color:
                #a83232;
        }


        .panel {

            margin-top:
                28px;

            overflow:
                hidden;

            border:
                1px solid
                #dde5de;

            border-radius:
                12px;

            background:
                #ffffff;
        }


        .panel-head {

            padding:
                16px
                18px;

            background:
                #08752c;

            color:
                #ffffff;
        }


        .panel-head h2 {

            margin: 0;

            font-size:
                17px;
        }


        table {

            width:
                100%;

            border-collapse:
                collapse;
        }


        th,
        td {

            padding:
                11px
                12px;

            border-bottom:
                1px solid
                #edf1ed;

            font-size:
                12px;

            text-align:
                left;
        }


        th {

            background:
                #f7f9f7;

            color:
                #59635b;
        }


        .good-box {

            margin-top:
                28px;

            padding:
                18px;

            border-left:
                5px solid
                #08752c;

            border-radius:
                8px;

            background:
                #eaf7ed;
        }


        .warning-box {

            margin-top:
                18px;

            padding:
                18px;

            border-left:
                5px solid
                #d8aa00;

            border-radius:
                8px;

            background:
                #fff8dc;
        }


        .btn {

            display:
                inline-block;

            margin-top:
                15px;

            padding:
                10px
                16px;

            border-radius:
                7px;

            background:
                #08752c;

            color:
                #ffffff;

            font-size:
                13px;

            font-weight:
                bold;

            text-decoration:
                none;
        }

    </style>

</head>


<body>


<div class="container">


    <h1>

        Research Workflow Integration

    </h1>


    <p class="subtitle">

        ASU-REMS Research master table integration

    </p>



    <?php foreach (
        $results
        as
        $result
    ): ?>


        <div
            class="
                result
                <?= htmlspecialchars(
                    $result['type'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            "
        >

            <?= htmlspecialchars(
                $result['message'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>


    <?php endforeach; ?>



    <?php if ($success): ?>


        <div class="good-box">

            <strong>
                Integration successful.
            </strong>

            <br><br>

            <code>
                research_proposals
            </code>

            is now the master table for the Research workflow.

        </div>


    <?php else: ?>


        <div class="warning-box">

            <strong>
                Integration was not completed.
            </strong>

            Check the error shown above before continuing.

        </div>


    <?php endif; ?>



    <?php if ($workflowRows): ?>


        <div class="panel">


            <div class="panel-head">

                <h2>

                    Research Workflow Records

                </h2>

            </div>


            <div style="overflow-x:auto;">


                <table>


                    <thead>


                    <tr>

                        <th>ID</th>

                        <th>Manuscript No.</th>

                        <th>Research Title</th>

                        <th>Old Status</th>

                        <th>Workflow Status</th>

                        <th>Stage</th>

                        <th>Track</th>

                        <th>Archived</th>

                    </tr>


                    </thead>


                    <tbody>


                    <?php foreach (
                        $workflowRows
                        as
                        $row
                    ): ?>


                        <tr>


                            <td>

                                <?= (int)$row[
                                    'research_id'
                                ]; ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row[
                                        'manuscript_no'
                                    ]
                                    ?? '—',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row[
                                        'research_title'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row[
                                        'status'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row[
                                        'workflow_status'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row[
                                        'workflow_stage'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row[
                                        'submission_track'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <td>

                                <?= (int)$row[
                                    'is_archived'
                                ] === 1
                                    ? 'Yes'
                                    : 'No'; ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        </div>


    <?php endif; ?>



    <?php if ($success): ?>


        <div class="warning-box">

            <strong>
                Important:
            </strong>

            Do not delete the existing
            <code>research_projects</code>
            table yet.

            It currently has no records, but we will leave it untouched
            until the new workflow is fully tested.

        </div>


        <a
            href="<?= htmlspecialchars(
                BASE_URL . '/dashboard.php',
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            class="btn"
        >

            Return to Dashboard

        </a>


    <?php endif; ?>


</div>


</body>

</html>