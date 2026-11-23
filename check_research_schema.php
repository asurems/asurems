<?php

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$user = currentUser();

if (
    !in_array(
        $user['role'] ?? '',
        ['admin', 'rps'],
        true
    )
) {
    http_response_code(403);
    exit('Access denied.');
}


/*
|--------------------------------------------------------------------------
| TABLES TO CHECK
|--------------------------------------------------------------------------
*/

$tablesToCheck = [
    'research_proposals',
    'research_projects',
    'research_status_history',
    'research_documents',
    'research_reviews',
    'manuscript_sequences'
];


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function tableExists(
    PDO $pdo,
    string $table
): bool {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");

    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}


function tableColumns(
    PDO $pdo,
    string $table
): array {

    $stmt = $pdo->prepare("
        SELECT
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            COLUMN_KEY,
            EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ");

    $stmt->execute([$table]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function tableRowCount(
    PDO $pdo,
    string $table
): int {

    $allowed = [
        'research_proposals',
        'research_projects',
        'research_status_history',
        'research_documents',
        'research_reviews',
        'manuscript_sequences'
    ];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    return (int)$pdo
        ->query("SELECT COUNT(*) FROM `{$table}`")
        ->fetchColumn();
}


function getColumnNames(
    array $columns
): array {

    return array_map(
        static fn(array $column): string =>
            $column['COLUMN_NAME'],
        $columns
    );
}


function hasColumns(
    array $actualColumns,
    array $requiredColumns
): bool {

    foreach ($requiredColumns as $required) {

        if (
            !in_array(
                $required,
                $actualColumns,
                true
            )
        ) {
            return false;
        }
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| LOAD SCHEMA
|--------------------------------------------------------------------------
*/

$schema = [];

foreach ($tablesToCheck as $table) {

    $exists =
        tableExists(
            $pdo,
            $table
        );

    $columns =
        $exists
            ? tableColumns(
                $pdo,
                $table
            )
            : [];

    $schema[$table] = [
        'exists' => $exists,
        'columns' => $columns,
        'column_names' =>
            getColumnNames($columns),
        'rows' =>
            $exists
                ? tableRowCount(
                    $pdo,
                    $table
                )
                : 0
    ];
}


/*
|--------------------------------------------------------------------------
| STATUS VALUES
|--------------------------------------------------------------------------
*/

$proposalStatuses = [];

$projectStatuses = [];


if (
    $schema[
        'research_proposals'
    ]['exists']
    &&
    in_array(
        'status',
        $schema[
            'research_proposals'
        ]['column_names'],
        true
    )
) {

    try {

        $proposalStatuses =
            $pdo->query("
                SELECT
                    status,
                    COUNT(*) AS total
                FROM research_proposals
                GROUP BY status
                ORDER BY status
            ")->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (Throwable $e) {

        $proposalStatuses = [];
    }
}


if (
    $schema[
        'research_projects'
    ]['exists']
    &&
    in_array(
        'current_status',
        $schema[
            'research_projects'
        ]['column_names'],
        true
    )
) {

    try {

        $projectStatuses =
            $pdo->query("
                SELECT
                    current_status,
                    COUNT(*) AS total
                FROM research_projects
                GROUP BY current_status
                ORDER BY current_status
            ")->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (Throwable $e) {

        $projectStatuses = [];
    }
}


/*
|--------------------------------------------------------------------------
| DETECTION
|--------------------------------------------------------------------------
*/

$proposalColumns =
    $schema[
        'research_proposals'
    ]['column_names'];

$projectColumns =
    $schema[
        'research_projects'
    ]['column_names'];

$historyColumns =
    $schema[
        'research_status_history'
    ]['column_names'];

$documentColumns =
    $schema[
        'research_documents'
    ]['column_names'];


/*
|--------------------------------------------------------------------------
| OLD RESEARCH MODULE
|--------------------------------------------------------------------------
*/

$legacyProposalStructure =
    hasColumns(
        $proposalColumns,
        [
            'research_id',
            'researcher_id',
            'research_title',
            'status'
        ]
    );


$legacyHistoryStructure =
    hasColumns(
        $historyColumns,
        [
            'research_id',
            'old_status',
            'new_status'
        ]
    );


$legacyDocumentStructure =
    hasColumns(
        $documentColumns,
        [
            'document_id',
            'research_id',
            'original_name',
            'stored_name'
        ]
    );


/*
|--------------------------------------------------------------------------
| NEW WORKFLOW
|--------------------------------------------------------------------------
*/

$newWorkflowStructure =
    hasColumns(
        $projectColumns,
        [
            'id',
            'researcher_id',
            'title',
            'submission_track',
            'current_status',
            'current_stage',
            'is_archived'
        ]
    );


$newHistoryStructure =
    hasColumns(
        $historyColumns,
        [
            'research_id',
            'from_status',
            'to_status',
            'action_code'
        ]
    );


/*
|--------------------------------------------------------------------------
| INTEGRATION STATUS
|--------------------------------------------------------------------------
*/

$twoResearchTables =
    $schema[
        'research_proposals'
    ]['exists']
    &&
    $schema[
        'research_projects'
    ]['exists'];

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
        ASU-REMS Research Schema Check
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f4f7f4;
            font-family: "Century Gothic", Arial, sans-serif;
            color: #273028;
        }

        .container {
            max-width: 1250px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            margin-top: 0;
            color: #707a72;
        }

        .summary {
            margin: 25px 0;
            padding: 20px;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid #dce5dd;
        }

        .status {
            display: inline-block;
            margin: 4px 5px 4px 0;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .good {
            background: #e6f5e9;
            color: #08752c;
        }

        .warn {
            background: #fff3cd;
            color: #806500;
        }

        .bad {
            background: #fde8e8;
            color: #b42318;
        }

        .table-card {
            margin-bottom: 25px;
            background: #ffffff;
            border: 1px solid #dce5dd;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-card-header {
            padding: 15px 18px;
            background: #08752c;
            color: white;
        }

        .table-card-header h2 {
            margin: 0;
            font-size: 17px;
        }

        .table-meta {
            margin-top: 5px;
            font-size: 12px;
            opacity: .9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #edf1ed;
            text-align: left;
            font-size: 12px;
        }

        th {
            background: #f7f9f7;
        }

        .missing {
            padding: 20px;
            color: #b42318;
            font-weight: bold;
        }

        .status-list {
            margin-top: 15px;
        }

        .status-table {
            max-width: 500px;
        }

        .next-box {
            padding: 20px;
            background: #fff8d9;
            border-left: 5px solid #e0b600;
            border-radius: 8px;
        }

        code {
            background: #eef2ee;
            padding: 2px 5px;
            border-radius: 4px;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>
        Research Database Structure Check
    </h1>

    <p class="subtitle">
        Diagnostic only — this page does not modify or delete database records.
    </p>


    <div class="summary">

        <h2>
            Integration Summary
        </h2>


        <?php if ($legacyProposalStructure): ?>

            <span class="status good">
                Existing research_proposals detected
            </span>

        <?php else: ?>

            <span class="status warn">
                Existing proposal structure not confirmed
            </span>

        <?php endif; ?>


        <?php if ($newWorkflowStructure): ?>

            <span class="status good">
                New research_projects workflow detected
            </span>

        <?php else: ?>

            <span class="status warn">
                New workflow structure incomplete
            </span>

        <?php endif; ?>


        <?php if ($twoResearchTables): ?>

            <span class="status warn">
                Two research master tables detected
            </span>

        <?php endif; ?>


        <?php if ($legacyHistoryStructure): ?>

            <span class="status good">
                Legacy history columns detected
            </span>

        <?php endif; ?>


        <?php if ($newHistoryStructure): ?>

            <span class="status good">
                New workflow history columns detected
            </span>

        <?php endif; ?>


        <?php if ($legacyDocumentStructure): ?>

            <span class="status good">
                Existing research document structure detected
            </span>

        <?php endif; ?>

    </div>


    <?php foreach (
        $schema
        as
        $tableName =>
        $tableInfo
    ): ?>

        <div class="table-card">

            <div class="table-card-header">

                <h2>
                    <?= htmlspecialchars(
                        $tableName,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </h2>

                <div class="table-meta">

                    <?php if (
                        $tableInfo['exists']
                    ): ?>

                        Exists •
                        <?= number_format(
                            $tableInfo['rows']
                        ); ?>
                        row(s)

                    <?php else: ?>

                        Table does not exist

                    <?php endif; ?>

                </div>

            </div>


            <?php if (
                !$tableInfo['exists']
            ): ?>

                <div class="missing">
                    TABLE NOT FOUND
                </div>

            <?php else: ?>

                <div style="overflow-x:auto;">

                    <table>

                        <thead>

                        <tr>
                            <th>Column</th>
                            <th>Type</th>
                            <th>Nullable</th>
                            <th>Default</th>
                            <th>Key</th>
                            <th>Extra</th>
                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach (
                            $tableInfo['columns']
                            as
                            $column
                        ): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $column[
                                                'COLUMN_NAME'
                                            ],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $column[
                                            'COLUMN_TYPE'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $column[
                                            'IS_NULLABLE'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string)(
                                            $column[
                                                'COLUMN_DEFAULT'
                                            ]
                                            ?? 'NULL'
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $column[
                                            'COLUMN_KEY'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $column[
                                            'EXTRA'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>


    <div class="summary">

        <h2>
            Existing Research Proposal Statuses
        </h2>

        <?php if (
            !$proposalStatuses
        ): ?>

            <p>
                No status records found.
            </p>

        <?php else: ?>

            <table class="status-table">

                <thead>

                <tr>
                    <th>Status</th>
                    <th>Total</th>
                </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $proposalStatuses
                    as
                    $row
                ): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                (string)$row['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int)$row['total']
                            ); ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


    <div class="summary">

        <h2>
            New Workflow Statuses
        </h2>

        <?php if (
            !$projectStatuses
        ): ?>

            <p>
                No workflow records found yet.
            </p>

        <?php else: ?>

            <table class="status-table">

                <thead>

                <tr>
                    <th>Status</th>
                    <th>Total</th>
                </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $projectStatuses
                    as
                    $row
                ): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                (string)$row[
                                    'current_status'
                                ],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int)$row['total']
                            ); ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


    <div class="next-box">

        <strong>
            Next:
        </strong>

        <?php if ($twoResearchTables): ?>

            Two research master tables exist.
            We need to integrate them before activating
            RPS review actions so Faculty and RPS do not
            work on different copies of a research record.

        <?php else: ?>

            The database structure can now be mapped
            to the RPS workflow.

        <?php endif; ?>

    </div>

</div>

</body>

</html>