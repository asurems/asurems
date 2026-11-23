<?php

require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    redirect(
        BASE_URL .
        '/admin/users/index.php'
    );
}


if (
    !verifyCsrfToken(
        $_POST['csrf_token'] ?? ''
    )
) {

    setFlash(
        'danger',
        'Invalid request.'
    );

    redirect(
        BASE_URL .
        '/admin/users/index.php'
    );
}


$userId =
    filter_var(
        $_POST['user_id'] ?? null,
        FILTER_VALIDATE_INT
    );


if (!$userId) {

    setFlash(
        'danger',
        'Invalid user.'
    );

    redirect(
        BASE_URL .
        '/admin/users/index.php'
    );
}


if (
    (int)$userId ===
    (int)$_SESSION['user_id']
) {

    setFlash(
        'warning',
        'You cannot deactivate your own account.'
    );

    redirect(
        BASE_URL .
        '/admin/users/index.php'
    );
}


$stmt = $pdo->prepare("
    SELECT status
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$user =
    $stmt->fetch();


if (!$user) {

    setFlash(
        'danger',
        'User not found.'
    );

    redirect(
        BASE_URL .
        '/admin/users/index.php'
    );
}


$newStatus =
    $user['status'] === 'Active'
        ? 'Inactive'
        : 'Active';


$update = $pdo->prepare("
    UPDATE users
    SET status = ?
    WHERE user_id = ?
");

$update->execute([
    $newStatus,
    $userId
]);


setFlash(
    'success',
    'User status changed to ' .
    $newStatus .
    '.'
);


redirect(
    BASE_URL .
    '/admin/users/index.php'
);