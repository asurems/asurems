<?php

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$user = currentUser();

if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required.');
}

$results = [];
$success = true;

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");

    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {

    $stmt = $pdo->prepare("
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

    return (int)$stmt->fetchColumn() > 0;
}

function addResult(
    array &$results,
    string $type,
    string $message
): void {

    $results[] = [
        'type' => $type,
        'message' => $message
    ];
}

try {

    /*
    |--------------------------------------------------------------------------
    | CREATE TABLE IF MISSING
    |--------------------------------------------------------------------------
    */

    if (
        !tableExists(
            $pdo,
            'research_workflow_reviews'
        )
    ) {

        $pdo->exec("
            CREATE TABLE research_workflow_reviews
            (
                review_id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                research_id INT UNSIGNED
                    NOT NULL,

                review_type VARCHAR(30)
                    NOT NULL,

                review_round INT UNSIGNED
                    NOT NULL DEFAULT 1,

                result VARCHAR(64)
                    NOT NULL,

                remarks TEXT
                    NULL,

                reviewed_by INT UNSIGNED
                    NULL,

                reviewed_at DATETIME
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (review_id),

                INDEX idx_rwr_research
                    (research_id),

                INDEX idx_rwr_type
                    (review_type),

                INDEX idx_rwr_result
                    (result),

                INDEX idx_rwr_reviewer
                    (reviewed_by)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");

        addResult(
            $results,
            'success',
            'research_workflow_reviews table created.'
        );

    } else {

        addResult(
            $results,
            'info',
            'research_workflow_reviews table already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REQUIRED COLUMNS
    |--------------------------------------------------------------------------
    */

    $columns = [

        'review_id' => "
            BIGINT UNSIGNED
            NOT NULL AUTO_INCREMENT
        ",

        'research_id' => "
            INT UNSIGNED
            NOT NULL
        ",

        'review_type' => "
            VARCHAR(30)
            NOT NULL DEFAULT 'ETHICS'
        ",

        'review_round' => "
            INT UNSIGNED
            NOT NULL DEFAULT 1
        ",

        'result' => "
            VARCHAR(64)
            NOT NULL DEFAULT ''
        ",

        'remarks' => "
            TEXT NULL
        ",

        'reviewed_by' => "
            INT UNSIGNED NULL
        ",

        'reviewed_at' => "
            DATETIME
            NOT NULL DEFAULT CURRENT_TIMESTAMP
        ",

        'created_at' => "
            TIMESTAMP
            NOT NULL DEFAULT CURRENT_TIMESTAMP
        "
    ];


    foreach ($columns as $column => $definition) {

        if (
            !columnExists(
                $pdo,
                'research_workflow_reviews',
                $column
            )
        ) {

            /*
             * review_id cannot safely be added automatically
             * as AUTO_INCREMENT if another primary key exists.
             */

            if ($column === 'review_id') {

                addResult(
                    $results,
                    'warning',
                    'review_id is missing. Please send a screenshot of the table structure.'
                );

                continue;
            }


            $pdo->exec("
                ALTER TABLE research_workflow_reviews
                ADD COLUMN {$column} {$definition}
            ");


            addResult(
                $results,
                'success',
                "Added missing column: {$column}"
            );

        } else {

            addResult(
                $results,
                'info',
                "{$column} exists."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TEST READ
    |--------------------------------------------------------------------------
    */

    $testStmt =
        $pdo->query("
            SELECT COUNT(*)
            FROM research_workflow_reviews
        ");

    $reviewCount =
        (int)$testStmt->fetchColumn();


    addResult(
        $results,
        'success',
        'Workflow review table is readable. Existing review records: '
        . $reviewCount
    );


    /*
    |--------------------------------------------------------------------------
    | CHECK RESEARCH #4
    |--------------------------------------------------------------------------
    */

    $researchStmt =
        $pdo->prepare("
            SELECT
                research_id,
                manuscript_no,
                status,
                workflow_status,
                workflow_stage,
                is_archived

            FROM research_proposals

            WHERE research_id = ?

            LIMIT 1
        ");

    $researchStmt->execute([4]);

    $research =
        $researchStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if ($research) {

        addResult(
            $results,
            'success',
            'Research #4 found. Current workflow status: '
            . ($research['workflow_status'] ?? 'NULL')
        );

    } else {

        addResult(
            $results,
            'warning',
            'Research #4 was not found.'
        );
    }


} catch (Throwable $e) {

    $success = false;

    addResult(
        $results,
        'danger',
        $e->getMessage()
    );
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
    Repair Ethics Workflow
</title>

<style>

body {
    margin: 0;
    padding: 35px;
    background: #f4f7f4;
    font-family: Arial, sans-serif;
    color: #283129;
}

.container {
    max-width: 900px;
    margin: 0 auto;
}

h1 {
    margin-bottom: 6px;
}

.subtitle {
    color: #737c75;
    margin-bottom: 25px;
}

.result {
    margin-bottom: 10px;
    padding: 13px 15px;
    border-radius: 8px;
    font-size: 13px;
}

.success {
    background: #e7f6eb;
    color: #08752c;
}

.info {
    background: #eaf2fa;
    color: #315f86;
}

.warning {
    background: #fff7d6;
    color: #776000;
}

.danger {
    background: #fdeaea;
    color: #a83232;
}

.final-box {
    margin-top: 25px;
    padding: 18px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #dce5dd;
}

a {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 16px;
    border-radius: 7px;
    background: #08752c;
    color: #fff;
    text-decoration: none;
    font-weight: bold;
}

</style>

</head>

<body>

<div class="container">

<h1>
    UREC / IACUC Workflow Repair
</h1>

<p class="subtitle">
    Checks and repairs the workflow review storage table.
</p>


<?php foreach ($results as $result): ?>

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


<div class="final-box">

<?php if ($success): ?>

<strong>
    Check completed.
</strong>

<p>
    You may now retry saving the
    UREC / IACUC result.
</p>

<a
    href="<?= htmlspecialchars(
        BASE_URL . '/research/view.php?id=4',
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>
    Return to Research #4
</a>

<?php else: ?>

<strong>
    Repair could not be completed.
</strong>

<p>
    Send a screenshot of the error shown above.
</p>

<?php endif; ?>

</div>

</div>

</body>

</html>