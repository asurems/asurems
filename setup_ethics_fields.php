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

function ethicsColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM information_schema.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = ?\n          AND COLUMN_NAME = ?\n    ");

    $stmt->execute([$table, $column]);

    return (int)$stmt->fetchColumn() > 0;
}

function ethicsResult(array &$results, string $type, string $message): void
{
    $results[] = [
        'type' => $type,
        'message' => $message,
    ];
}

try {
    $columns = [
        'ethics_result' => "VARCHAR(30) NULL AFTER workflow_updated_at",
        'ethics_remarks' => "TEXT NULL AFTER ethics_result",
        'ethics_reviewed_by' => "INT UNSIGNED NULL AFTER ethics_remarks",
        'ethics_reviewed_at' => "DATETIME NULL AFTER ethics_reviewed_by",
    ];

    foreach ($columns as $column => $definition) {
        if (!ethicsColumnExists($pdo, 'research_proposals', $column)) {
            $pdo->exec("ALTER TABLE research_proposals ADD COLUMN {$column} {$definition}");
            ethicsResult($results, 'success', "Added {$column}.");
        } else {
            ethicsResult($results, 'info', "{$column} already exists.");
        }
    }

    ethicsResult(
        $results,
        'success',
        'UREC/IACUC storage fields are ready. No existing research record was deleted.'
    );
} catch (Throwable $e) {
    $success = false;
    ethicsResult($results, 'danger', $e->getMessage());
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UREC/IACUC Setup</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 35px; background: #f4f7f4; font-family: "Century Gothic", Arial, sans-serif; color: #273028; }
        .container { max-width: 850px; margin: 0 auto; }
        h1 { margin: 0 0 6px; }
        .subtitle { margin: 0 0 24px; color: #717a73; }
        .result { margin-bottom: 10px; padding: 13px 15px; border-radius: 9px; font-size: 13px; }
        .success { background: #e7f6eb; color: #08752c; }
        .info { background: #eaf2fa; color: #315f86; }
        .danger { background: #fdeaea; color: #a83232; }
        .box { margin-top: 25px; padding: 18px; border: 1px solid #dce5dd; border-radius: 11px; background: #fff; }
        .btn { display: inline-block; margin-top: 14px; padding: 10px 15px; border-radius: 8px; background: #08752c; color: #fff; text-decoration: none; font-weight: bold; font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <h1>UREC / IACUC Storage Setup</h1>
    <p class="subtitle">Adds stable ethics-review fields to the existing research_proposals master table.</p>

    <?php foreach ($results as $result): ?>
        <div class="result <?= htmlspecialchars($result['type'], ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endforeach; ?>

    <div class="box">
        <?php if ($success): ?>
            <strong>Setup complete.</strong>
            <p>You may now replace research/view.php with the updated version and test the UREC/IACUC result again.</p>
            <a class="btn" href="<?= htmlspecialchars(BASE_URL . '/dashboard.php', ENT_QUOTES, 'UTF-8'); ?>">Return to Dashboard</a>
        <?php else: ?>
            <strong>Setup stopped.</strong>
            <p>Use the error shown above before continuing.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
