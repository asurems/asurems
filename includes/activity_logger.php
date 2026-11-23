<?php
/**
 * ASU-REMS — successful sign-in history.
 *
 * Call once AFTER credentials have been verified and the account is Active,
 * and BEFORE redirecting to the dashboard. Each invocation INSERTs a new row.
 * Reloads, visits to login.php during an existing session, and failed logins
 * do not call this function and therefore do not create audit events.
 *
 * Requires the existing login_activity_logs table.
 */

if (!function_exists('remsLogSuccessfulLogin')) {
    function remsLogSuccessfulLogin(PDO $pdo, array $user): void
    {
        $userId = (int)($user['user_id'] ?? 0);
        $role = trim((string)($user['role'] ?? ''));
        if ($userId <= 0 || $role === '') {
            error_log('ASU-REMS login audit: missing user ID or role.');
            return;
        }

        $nameParts = [];
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $part = trim((string)($user[$field] ?? ''));
            if ($part !== '') {
                $nameParts[] = $part;
            }
        }
        $fullName = trim(implode(' ', $nameParts));
        if ($fullName === '') {
            $fullName = trim((string)($user['full_name'] ?? $user['name'] ?? $user['username'] ?? ''));
        }
        if ($fullName === '') {
            $fullName = 'User #' . $userId;
        }

        // Use Philippine local time for the DATETIME column displayed in Activity Logs.
        $loggedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))
            ->format('Y-m-d H:i:s');

        try {
            $statement = $pdo->prepare(
                'INSERT INTO login_activity_logs '
                . '(user_id, full_name, email, user_role, login_at) '
                . 'VALUES (:user_id, :full_name, :email, :user_role, :login_at)'
            );
            $statement->execute([
                ':user_id' => $userId,
                ':full_name' => $fullName,
                ':email' => trim((string)($user['email'] ?? '')),
                ':user_role' => $role,
                ':login_at' => $loggedAt,
            ]);
        } catch (Throwable $exception) {
            // Authentication should continue if the log table is temporarily unavailable.
            // An error is written to PHP's error log for diagnosis.
            error_log('ASU-REMS login audit INSERT failed: ' . $exception->getMessage());
        }
    }
}
