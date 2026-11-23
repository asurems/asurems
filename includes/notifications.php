<?php

/*
|--------------------------------------------------------------------------
| ASU-REMS NOTIFICATION HELPERS
|--------------------------------------------------------------------------
|
| Use this file after includes/auth.php or config/database.php is already loaded.
|
*/


function ensureNotificationsTable(
    PDO $pdo
): void {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,

            user_id INT NOT NULL,

            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,

            module VARCHAR(60) DEFAULT NULL,
            record_id INT DEFAULT NULL,
            action_url VARCHAR(255) DEFAULT NULL,

            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME DEFAULT NULL,

            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_notifications_user_read (user_id, is_read),
            INDEX idx_notifications_created_at (created_at),
            INDEX idx_notifications_module_record (module, record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}


function createNotification(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    ?string $module = null,
    ?int $recordId = null,
    ?string $actionUrl = null
): bool {

    ensureNotificationsTable($pdo);

    if ($userId <= 0) {
        return false;
    }

    $title =
        trim($title);

    $message =
        trim($message);

    if (
        $title === ''
        ||
        $message === ''
    ) {
        return false;
    }

    $stmt =
        $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                title,
                message,
                module,
                record_id,
                action_url,
                is_read,
                created_at
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                0,
                NOW()
            )
        ");

    return $stmt->execute([
        $userId,
        $title,
        $message,
        $module,
        $recordId,
        $actionUrl
    ]);
}


function notifyUsersByRole(
    PDO $pdo,
    array $roles,
    string $title,
    string $message,
    ?string $module = null,
    ?int $recordId = null,
    ?string $actionUrl = null,
    ?int $excludeUserId = null
): int {

    ensureNotificationsTable($pdo);

    $roles =
        array_values(
            array_filter(
                array_unique($roles)
            )
        );

    if (!$roles) {
        return 0;
    }

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($roles),
                '?'
            )
        );

    $params =
        $roles;

    $excludeSql = '';

    if (
        $excludeUserId !== null
        &&
        $excludeUserId > 0
    ) {

        $excludeSql =
            " AND user_id <> ?";

        $params[] =
            $excludeUserId;
    }

    $stmt =
        $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE role IN ({$placeholders})
              AND status = 'Active'
              {$excludeSql}
        ");

    $stmt->execute($params);

    $created = 0;

    foreach (
        $stmt->fetchAll(PDO::FETCH_COLUMN)
        as
        $userId
    ) {

        if (
            createNotification(
                $pdo,
                (int)$userId,
                $title,
                $message,
                $module,
                $recordId,
                $actionUrl
            )
        ) {

            $created++;
        }
    }

    return $created;
}


function unreadNotificationCount(
    PDO $pdo,
    int $userId
): int {

    ensureNotificationsTable($pdo);

    if ($userId <= 0) {
        return 0;
    }

    $stmt =
        $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
              AND is_read = 0
        ");

    $stmt->execute([
        $userId
    ]);

    return (int)$stmt->fetchColumn();
}


function markNotificationAsRead(
    PDO $pdo,
    int $notificationId,
    int $userId
): bool {

    ensureNotificationsTable($pdo);

    if (
        $notificationId <= 0
        ||
        $userId <= 0
    ) {
        return false;
    }

    $stmt =
        $pdo->prepare("
            UPDATE notifications
            SET
                is_read = 1,
                read_at = NOW()
            WHERE notification_id = ?
              AND user_id = ?
        ");

    return $stmt->execute([
        $notificationId,
        $userId
    ]);
}


function markAllNotificationsAsRead(
    PDO $pdo,
    int $userId
): bool {

    ensureNotificationsTable($pdo);

    if ($userId <= 0) {
        return false;
    }

    $stmt =
        $pdo->prepare("
            UPDATE notifications
            SET
                is_read = 1,
                read_at = NOW()
            WHERE user_id = ?
              AND is_read = 0
        ");

    return $stmt->execute([
        $userId
    ]);
}


function deleteNotification(
    PDO $pdo,
    int $notificationId,
    int $userId
): bool {

    ensureNotificationsTable($pdo);

    if (
        $notificationId <= 0
        ||
        $userId <= 0
    ) {
        return false;
    }

    $stmt =
        $pdo->prepare("
            DELETE FROM notifications
            WHERE notification_id = ?
              AND user_id = ?
        ");

    return $stmt->execute([
        $notificationId,
        $userId
    ]);
}


function deleteReadNotifications(
    PDO $pdo,
    int $userId
): bool {

    ensureNotificationsTable($pdo);

    if ($userId <= 0) {
        return false;
    }

    $stmt =
        $pdo->prepare("
            DELETE FROM notifications
            WHERE user_id = ?
              AND is_read = 1
        ");

    return $stmt->execute([
        $userId
    ]);
}
